<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Strings;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\SiteStringsSaved;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringStore;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Edits the strings a page's template declares with @string (ADR-0047), in one language. Null
 * removes the edit, so the template's code text shows again. A page's own strings are saved with
 * pages.save, or to its draft with pages.save_draft, so revisions and drafts cover them (ADR-0048).
 * Site strings are stored on the home page whichever page they are edited from, and are never
 * drafted: every page shares them.
 */
final class SaveStrings extends Action
{
    use ResolvesPages;

    private const int MAX_LENGTH = 10000;

    public function __construct(
        private readonly StringCatalog $catalog,
        private readonly StringStore $store,
        private readonly LanguageRegistry $languages,
        private readonly LanguageContext $context,
        private readonly RootPage $root,
    ) {}

    public static function name(): string
    {
        return 'strings.save';
    }

    public static function description(): string
    {
        return 'Edit the texts a page\'s template declares (headlines, button labels, footer notes), in one language. null puts the template\'s own text back. get_page lists them under "strings".';
    }

    public static function permission(): string
    {
        return 'pages.edit';
    }

    public static function rules(): array
    {
        return [
            'page' => ['required'],
            'language' => ['sometimes', 'nullable', 'string'],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:'.self::MAX_LENGTH],
            'draft' => ['sometimes', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'language' => $schema->string()->description('Language code. Defaults to the current language.'),
            'values' => $schema->object()->description('Key => text, e.g. {"hero.title": "Produções audiovisuais"}; null removes the edit.')->required(),
            'draft' => $schema->boolean()->description('Save the page\'s own strings to its draft; pages.publish_draft makes them live. Site strings cannot be drafted.'),
        ];
    }

    /**
     * @return array<string, mixed> what was set, by key (null for a reset)
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = $this->resolvePageReference($input->get('page'));
        $context->authorize('pages.edit', $page);
        $language = $input->string('language') !== null ? $this->languages->get($input->string('language')) : $this->context->current();
        $declared = $this->catalog->forTemplate((string) $page->template()->name) + $this->catalog->siteStrings();
        $values = Value::map($input->get('values'));
        $unknown = array_diff(array_keys($values), array_keys($declared));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['values' => 'The template of this page declares no string ['.implode(', ', $unknown).'].']);
        }

        $site = array_filter($values, fn (string $key): bool => $declared[$key]->isSite(), ARRAY_FILTER_USE_KEY);
        $own = array_diff_key($values, $site);

        if ($input->bool('draft') && $site !== []) {
            throw ValidationException::withMessages(['values' => 'Site texts are shared by every page and are not drafted: '.implode(', ', array_keys($site)).'.']);
        }

        $this->saveSiteStrings($site, $language, $context);

        if ($site !== []) {
            $context->event(new SiteStringsSaved(array_keys($site), (string) $language->code));
        }

        if ($own !== []) {
            $strings = [(string) $language->code => $own];
            $input->bool('draft')
                ? $context->run(SaveDraft::class, ['page' => $page->id, 'strings' => $strings])
                : $context->run(SavePage::class, ['id' => $page->id, 'strings' => $strings]);
        }

        return $values;
    }

    /**
     * Site strings, on the home page.
     *
     * @param  array<string, mixed>  $values
     */
    private function saveSiteStrings(array $values, Language $language, ActionContext $context): void
    {
        $owner = (int) $this->root->id();

        foreach ($values as $key => $text) {
            $where = ['page_id' => $owner, 'key' => $key, 'language_id' => $language->id];
            DB::table(Tables::STRINGS)->where($where)->delete();

            if ($text !== null && $text !== '') {
                DB::table(Tables::STRINGS)->insert($where + ['value' => Value::string($text), 'updated_by' => $context->actor->kind.':'.$context->actor->id, 'updated_at' => now()]);
            }

            $context->changes->updated('string', $owner, $text === null || $text === '' ? "Reset string {$key} [{$language->code}]" : "Set string {$key} [{$language->code}]", ['key' => $key, 'value' => $text]);
        }

        $this->store->forget($owner);
    }
}
