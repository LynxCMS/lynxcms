<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Tables;

/**
 * Sets when a page is published or unpublished in one language, or in every language with
 * language "*" (ADR-0036). In the default language that is the page's unpublished status; in
 * others, whether the language is active. lynx:schedule:run applies due entries every minute.
 */
final class SchedulePage extends Action
{
    use ResolvesPages;

    public const array ACTIONS = ['publish', 'unpublish'];

    /** The language value that schedules every language at once. */
    public const string ALL_LANGUAGES = '*';

    public function __construct(private readonly LanguageRegistry $languages) {}

    public static function name(): string
    {
        return 'pages.schedule';
    }

    public static function permission(): string
    {
        return 'pages.publish';
    }

    public static function description(): string
    {
        return 'Schedule publishing and unpublishing of a page in one language, or in every language with language "*". Give publish_at and/or unpublish_at (future date-times); a key given as null clears that entry. In the default language this changes the unpublished status, in others whether the page is published in that language.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'language' => ['sometimes', 'string'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'unpublish_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'language' => $schema->string()->description('Language code, or "*" for every language. Defaults to the default language.'),
            'publish_at' => $schema->string()->description('When to publish, ISO 8601, or null to clear.'),
            'unpublish_at' => $schema->string()->description('When to unpublish, ISO 8601, or null to clear.'),
        ];
    }

    /**
     * @return array<string, string|null>|array<string, array<string, string|null>> action => ISO time
     *                                                                              for the entries changed; language code => those with "*"
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = $this->resolvePageReference($input->get('page'));
        $context->authorize('pages.publish', $page);
        $isAll = $input->string('language') === self::ALL_LANGUAGES;
        $by = $context->actor->kind.($context->actor->id === null ? '' : ':'.$context->actor->id);
        $planned = [];

        // Every language is checked before any is stored, so one refusal changes nothing.
        foreach ($this->targetLanguages($input, $isAll) as $language) {
            $planned[(string) $language->code] = [$language, $this->times($input, $page, (int) $language->id)];
        }

        $result = [];

        foreach ($planned as $code => [$language, $times]) {
            $result[$code] = $this->apply($page, $language, $times, $by, $context);
        }

        return $isAll ? $result : (array_first($result) ?? []);
    }

    /**
     * @return list<Language>
     */
    private function targetLanguages(Input $input, bool $isAll): array
    {
        if ($isAll) {
            return array_values($this->languages->all()->all());
        }

        return [$this->languages->find($input->string('language') ?? $this->languages->default()->code)
            ?? throw ValidationException::withMessages(['language' => 'Language ['.$input->string('language').'] does not exist.'])];
    }

    /**
     * Stores one language's entries and records the change.
     *
     * @param  array<string, CarbonImmutable|null>  $times
     * @return array<string, string|null>
     */
    private function apply(Page $page, Language $language, array $times, string $by, ActionContext $context): array
    {
        foreach ($times as $action => $at) {
            $this->store((int) $page->id, (int) $language->id, $action, $at, $by);
        }

        $iso = array_map(fn (?CarbonImmutable $at): ?string => $at?->toIso8601String(), $times);
        $summary = implode(', ', array_map(fn (string $action, ?string $at): string => $action.' '.($at ?? 'cleared'), array_keys($iso), $iso));
        $context->changes->updated('page', $page->id, "Schedule page {$page->name()} [{$language->code}]: {$summary}", [
            'language' => $language->code,
            'schedule' => $iso,
        ]);

        return $iso;
    }

    /**
     * The entries the input sets (a time) or clears (null), checked against each other and the
     * entries it leaves alone.
     *
     * @return array<string, CarbonImmutable|null>
     */
    private function times(Input $input, Page $page, int $languageId): array
    {
        $times = [];

        foreach (self::ACTIONS as $action) {
            if ($input->has($action.'_at')) {
                $value = $input->string($action.'_at');
                $times[$action] = $value === null || $value === '' ? null : CarbonImmutable::parse($value);
            }
        }

        if ($times === []) {
            throw ValidationException::withMessages(['publish_at' => 'Give publish_at, unpublish_at or both.']);
        }

        foreach (array_filter($times) as $action => $at) {
            if ($at->isPast()) {
                throw ValidationException::withMessages([$action.'_at' => "The {$action} time must be in the future."]);
            }
        }

        $publish = array_key_exists('publish', $times) ? $times['publish'] : $this->stored((int) $page->id, $languageId, 'publish');
        $unpublish = array_key_exists('unpublish', $times) ? $times['unpublish'] : $this->stored((int) $page->id, $languageId, 'unpublish');

        if ($publish !== null && $unpublish !== null && $unpublish->lessThanOrEqualTo($publish)) {
            throw ValidationException::withMessages(['unpublish_at' => 'The unpublish time must be after the publish time.']);
        }

        return $times;
    }

    private function stored(int $pageId, int $languageId, string $action): ?CarbonImmutable
    {
        $at = DB::table(Tables::SCHEDULES)->where('page_id', $pageId)->where('language_id', $languageId)->where('action', $action)->value('at');

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    private function store(int $pageId, int $languageId, string $action, ?CarbonImmutable $at, string $by): void
    {
        $key = ['page_id' => $pageId, 'language_id' => $languageId, 'action' => $action];

        if ($at === null) {
            DB::table(Tables::SCHEDULES)->where($key)->delete();

            return;
        }

        DB::table(Tables::SCHEDULES)->updateOrInsert($key, ['at' => $at, 'created_by' => $by, 'error' => null, 'failed_at' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
