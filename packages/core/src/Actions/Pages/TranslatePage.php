<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\Items\ItemTranslations;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Machine-translates a page into a language: only translatable fields that have no value there
 * (unless overwrite), in one batched provider call. Values are saved with origin "machine" and
 * the hash of their source text, so human edits are never overwritten and outdated translations
 * can be found later (ADR-0016). The page gets a name in the target language if it has none.
 */
final class TranslatePage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageGuard $guard,
        private readonly LanguageRegistry $languages,
        private readonly ItemTranslations $items,
    ) {}

    public static function name(): string
    {
        return 'pages.translate';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function description(): string
    {
        return 'Machine-translate a page into a language. Only fields with no value in that language are translated (set overwrite to replace machine translations). Values are marked as machine translations for review. The page also gets a URL name in that language if it has none.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'to' => ['required', 'string'],
            'from' => ['nullable', 'string'],
            'fields' => ['sometimes', 'array'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Page id, default-language path or page name.')->required(),
            'to' => $schema->string()->description('Target language code.')->required(),
            'from' => $schema->string()->description('Source language code. Default: the default language.'),
            'fields' => $schema->array()->items($schema->string())->description('Only these fields. Default: all translatable fields.'),
            'overwrite' => $schema->boolean()->description('Also replace existing machine translations. Human translations are never replaced.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $provider = $this->provider();
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertEditable($page);
        $context->authorize('pages.edit', $page);
        $to = $this->languages->get((string) $input->string('to'));
        $from = $this->languages->get($input->string('from') ?? $this->languages->default()->code);

        if ($to->id === $from->id) {
            throw ValidationException::withMessages(['to' => 'Source and target language are the same.']);
        }

        [$texts, $formats] = $this->sources($page, $from, $to, $input->array('fields'), $input->bool('overwrite'));

        if ($texts === []) {
            $context->changes->updated('page', $page->id, "Nothing to translate on page {$page->name()} into {$to->code}");

            return $page;
        }

        $translated = $provider->translate($texts, $from->appLocale(), $to->appLocale(), $formats, [
            'provider_code' => $to->provider_codes[$provider->name()] ?? null,
        ]);

        $saved = $context->run(SavePage::class, [
            'page' => $page,
            'origin' => 'machine',
            'provider' => $provider->name(),
            'source_hashes' => $this->applyTranslations($page, $to, $texts, $translated),
        ]);

        return $saved instanceof Page ? $saved : throw new LogicException('SavePage did not return a page.');
    }

    private function provider(): TranslationProvider
    {
        if (! app()->bound(TranslationProvider::class)) {
            throw ValidationException::withMessages(['provider' => 'No translation provider is configured. Install lynxcms/ai or bind a TranslationProvider.']);
        }

        return app(TranslationProvider::class);
    }

    /**
     * The texts to translate and their formats, by field name (item subfields by their own keys).
     *
     * @param  array<array-key, mixed>  $only  field names; empty for every field
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function sources(Page $page, Language $from, Language $to, array $only, bool $overwrite): array
    {
        $origins = DB::table(Tables::FIELD_VALUE_META)
            ->where('page_id', $page->id)->where('language_id', $to->id)->pluck('origin', 'field_id');
        $texts = [];
        $formats = [];

        foreach ($page->template()->fields as $field) {
            if ($only !== [] && ! in_array($field->name, $only, true)) {
                continue;
            }

            if (! $field->isTranslatable()) {
                // Item fields hold translatable subfields of their own (ADR-0044).
                [$itemTexts, $itemFormats] = $this->items->collect($page, $field, $from, $to, $overwrite);
                $texts += $itemTexts;
                $formats += $itemFormats;
            } elseif ($this->needsTranslation($page, $field, $from, $to, $overwrite && ($origins[$field->id] ?? 'human') === 'machine')) {
                $texts[$field->name] = Value::string($page->getLanguageValue($from, $field->name));
                $formats[$field->name] = Value::string($field->config()['format'] ?? 'plain');
            }
        }

        return [$texts, $formats];
    }

    /** A source text exists, and the target has no value or one a machine wrote that may be replaced. */
    private function needsTranslation(Page $page, Field $field, Language $from, Language $to, bool $canReplace): bool
    {
        $source = $page->getLanguageValue($from, $field->name);

        if (! is_string($source) || trim($source) === '') {
            return false;
        }

        return $page->getLanguageValue($to, $field->name) === null || $canReplace;
    }

    /**
     * Writes the translations into the page and names it in the language when it has no name.
     *
     * @param  array<string, string>  $texts
     * @param  array<string, string>  $translated
     * @return array<string, string> source hash by field name
     */
    private function applyTranslations(Page $page, Language $to, array $texts, array $translated): array
    {
        $hashes = [];

        foreach ($page->template()->fields as $field) {
            if (! $field->isTranslatable()) {
                $this->items->apply($page, $field, $to, $translated);
            }
        }

        foreach ($texts as $fieldName => $source) {
            if (isset($translated[$fieldName]) && ! str_contains($fieldName, '.')) {
                $page->setLanguageValue($to, $fieldName, $translated[$fieldName]);
                $hashes[$fieldName] = hash('sha256', $source);
            }
        }

        if (! $page->isActiveIn($to) && isset($translated['title'])) {
            $page->setLanguageName($to, $translated['title'], true);
        }

        return $hashes;
    }
}
