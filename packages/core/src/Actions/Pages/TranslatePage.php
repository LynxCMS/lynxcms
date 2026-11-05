<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\Items\ItemTranslations;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\EditLocks;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Translation\TranslationProviders;

/**
 * Machine-translates a page into a language: only translatable fields that have no value there
 * (unless overwrite), in one batched provider call. Values are saved with origin "machine" and
 * the hash of their source text, so human edits are never overwritten and outdated translations
 * can be found later (ADR-0016). The page gets a name in the target language if it has none.
 * With draft, the translation goes into the page's draft for review instead, from the draft's
 * own source text, and names nothing (ADR-0100). A dry run counts the texts and calls no provider.
 */
final class TranslatePage extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly PageGuard $guard,
        private readonly LanguageRegistry $languages,
        private readonly ItemTranslations $items,
        private readonly TranslationProviders $providers,
        private readonly Drafts $drafts,
        private readonly PageLoader $loader,
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
        return 'Machine-translate a page into a language. Only fields with no value in that language are translated (set overwrite to replace machine translations). Values are marked as machine translations for review. The page also gets a URL name in that language if it has none. Set draft to write into the page\'s draft instead, for review before publishing.';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'to' => ['required', 'string'],
            'from' => ['nullable', 'string'],
            'fields' => ['sometimes', 'array'],
            'overwrite' => ['sometimes', 'boolean'],
            'draft' => ['sometimes', 'boolean'],
            'provider' => ['nullable', 'string'],
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
            'draft' => $schema->boolean()->description('Write into the page\'s draft, created if missing, instead of the live page.'),
            'provider' => $schema->string()->description('Translation provider name. Default: the site\'s default provider.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertEditable($page);
        $context->authorize('pages.edit', $page);
        $to = $this->languages->get((string) $input->string('to'));
        $from = $this->languages->get($input->string('from') ?? $this->languages->default()->code);

        if ($to->id === $from->id) {
            throw ValidationException::withMessages(['to' => 'Source and target language are the same.']);
        }

        $provider = $this->providers->get($input->string('provider'));
        $isDraft = $input->bool('draft');
        $target = $isDraft ? $this->draftOf($page, $context) : $page;
        [$texts, $formats] = $this->sources($target, $from, $to, $input->array('fields'), $input->bool('overwrite'));
        $where = $isDraft ? ' in its draft' : '';

        if ($texts === []) {
            $context->changes->updated('page', $page->id, "Nothing to translate on page {$page->name()} into {$to->code}{$where}");

            return $target;
        }

        // Recorded first: the activity log files an action under its first change, so under the live page.
        $context->changes->updated('page', $page->id, "Translate page {$page->name()} into {$to->code}{$where}", [
            'texts' => count($texts),
            'characters' => array_sum(array_map(mb_strlen(...), $texts)),
            'provider' => $provider->name(),
        ]);

        if ($context->dryRun) {
            return $target;
        }

        $translated = $provider->translate($texts, $from->appLocale(), $to->appLocale(), $formats, [
            'provider_code' => $to->provider_codes[$provider->name()] ?? null,
        ]);

        $saved = $context->run(SavePage::class, [
            'page' => $target,
            'origin' => 'machine',
            'provider' => $provider->name(),
            'source_language' => $from->code,
            'source_hashes' => $this->applyTranslations($target, $to, $texts, $translated, nameIt: ! $isDraft),
        ]);

        return $saved instanceof Page ? $saved : throw new LogicException('SavePage did not return a page.');
    }

    /** The page's draft, created from the live page when it has none. */
    private function draftOf(Page $page, ActionContext $context): Page
    {
        $draftId = $this->drafts->of((int) $page->id) ?? $this->drafts->create($page, EditLocks::holder($context->actor));

        return $this->loader->find($draftId) ?? throw new LogicException("The draft of page [{$page->name()}] could not be read.");
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
     * Writes the translations into the page and, when asked, names it in the language when it has
     * no name there. Drafts have no names (ADR-0039).
     *
     * @param  array<string, string>  $texts
     * @param  array<string, string>  $translated
     * @return array<string, string> source hash by field name
     */
    private function applyTranslations(Page $page, Language $to, array $texts, array $translated, bool $nameIt): array
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

        if ($nameIt && ! $page->isActiveIn($to) && isset($translated['title'])) {
            $page->setLanguageName($to, $translated['title'], true);
        }

        return $hashes;
    }
}
