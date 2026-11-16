<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaOwners;
use LynxCms\Core\Search\Plurals\WordReducers;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * What the search index holds (ADR-0120), for the admin's Search index screen and the
 * search_inspect read tool: rows per language, the fields each template indexes, a page's rows.
 *
 * @internal
 */
final readonly class SearchIndexReport
{
    public function __construct(
        private LanguageRegistry $languages,
        private TemplateRegistry $templates,
        private WordReducers $reducers,
        private FieldRegistry $fields,
        private SchemaOwners $owners,
    ) {}

    /**
     * Each language with how many pages have a row, out of the pages there are (drafts aside), and
     * how many have their own title in it: the others are indexed with their fallback text, so
     * visitors find them in that language too. own is null when titles are shared.
     *
     * @return list<array{code: string, name: string, rows: int, pages: int, own: ?int, plurals: bool}>
     */
    public function languages(): array
    {
        $rows = DB::table(Tables::SEARCH)->selectRaw('language_id, count(*) as total')->groupBy('language_id')->pluck('total', 'language_id');
        $pages = DB::table(Tables::PAGES)->whereNull('draft_of')->count();
        $own = $this->ownTitles();

        return array_values($this->languages->all()->map(fn (Language $language): array => [
            'code' => (string) $language->code,
            'name' => (string) $language->name,
            'rows' => Value::int($rows[$language->id] ?? 0),
            'pages' => $pages,
            'own' => $own === null ? null : Value::int($own[$language->id] ?? 0),
            'plurals' => $this->reducers->for($language) !== null,
        ])->all());
    }

    /**
     * Each template with its pages, the fields it indexes, and its owner: the module or Lynx for
     * system templates (ADR-0115), null for the site's own.
     *
     * @return list<array{name: string, label: string, has_url: bool, pages: int, fields: list<string>, owner: ?string}>
     */
    public function templates(): array
    {
        $pages = DB::table(Tables::PAGES)->whereNull('draft_of')->selectRaw('template_id, count(*) as total')->groupBy('template_id')->pluck('total', 'template_id');

        return array_values($this->templates->all()->map(fn (Template $template): array => [
            'name' => (string) $template->name,
            'label' => (string) $template->label,
            'has_url' => $template->hasUrl(),
            'pages' => Value::int($pages[$template->id] ?? 0),
            'fields' => $this->indexedFields($template),
            'owner' => $this->owners->ownerOfTemplate((string) $template->name),
        ])->all());
    }

    /**
     * What a page's rows hold, by language code.
     *
     * @return array<string, string>
     */
    public function page(int $pageId): array
    {
        $contents = DB::table(Tables::SEARCH)->where('page_id', $pageId)->pluck('content', 'language_id');
        $rows = [];

        foreach ($this->languages->all() as $language) {
            /** @var Language $language */
            if (isset($contents[$language->id])) {
                $rows[(string) $language->code] = Value::string($contents[$language->id]);
            }
        }

        return $rows;
    }

    /**
     * Pages with a title of their own, by language id, or null when the title is shared.
     *
     * @return Collection<int|string, mixed>|null
     */
    private function ownTitles(): ?Collection
    {
        $title = $this->fields->find('title');

        if ($title === null || ! $title->isTranslatable()) {
            return null;
        }

        return DB::table($title->table())
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', $title->table().'.page_id')
            ->whereNull(Tables::PAGES.'.draft_of')
            ->where($title->table().'.data', '!=', '')
            ->selectRaw('language_id, count(distinct page_id) as total')
            ->groupBy('language_id')
            ->pluck('total', 'language_id');
    }

    /** @return list<string> */
    private function indexedFields(Template $template): array
    {
        return array_values(array_map(strval(...), $this->templates->fields($template)
            ->filter(fn (TemplateField $field, string $name): bool => SearchText::indexes($name, $field))
            ->keys()->all()));
    }
}
