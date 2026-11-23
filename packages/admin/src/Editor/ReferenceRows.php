<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\References\PageReference;
use LynxCms\Core\Pages\References\PageReferenceIndex;
use LynxCms\Core\Query\Backlinks;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;

/**
 * The References tab's rows (ADR-0145): the pages that reference a page, limited in the query to
 * pages the user may view, each with links to the fields and items that hold the references.
 *
 * @internal
 */
final readonly class ReferenceRows
{
    public function __construct(
        private Backlinks $backlinks,
        private PageFinder $finder,
        private PageReferenceIndex $index,
        private BacklinkFilter $filter,
        private FieldRegistry $fields,
        private LanguageRegistry $languages,
    ) {}

    /**
     * @return list<Page> lowest id first
     */
    public function pages(int $pageId, int $limit, ?PageScope $scope): array
    {
        $ids = $this->backlinks->pageIds($pageId);

        if ($ids === []) {
            return [];
        }

        $selector = trim($this->filter->selector().', id='.implode('|', $ids).', include=all, sort=id, limit='.$limit, ', ');

        return array_values($this->finder->find($selector, maxLimit: $limit, scope: $scope)->all());
    }

    /**
     * @param  list<Page>  $pages
     * @return list<array{id: int, title: string, context: string, locations: list<array{label: string, url: string}>}>
     */
    public function rows(int $pageId, array $pages): array
    {
        $locations = $this->index->locations($pageId, array_map(fn (Page $page): int => (int) $page->id, $pages));
        $rows = [];

        foreach ($pages as $page) {
            $links = [];

            foreach ($locations[(int) $page->id] ?? [] as $reference) {
                $link = $this->location($page, $reference);
                $links[$link['url']] = $link;
            }

            $rows[] = LinkedPages::row($page) + ['locations' => array_values($links)];
        }

        return $rows;
    }

    /**
     * @return array{label: string, url: string}
     */
    public function location(Page $page, PageReference $reference): array
    {
        $name = (string) $this->fields->find($reference->fieldId)?->name;
        $label = $page->templateField($name)?->label() ?: $name;

        if ($reference->itemKey !== null) {
            $label = Value::string(__('lynx-admin::editor.references.item', ['field' => $label, 'number' => $this->itemNumber($page, $name, $reference->itemKey)]));
        }

        $language = $reference->languageId === null ? null : $this->languages->find($reference->languageId)?->code;

        return ['label' => $label, 'url' => FieldAddress::url($page, $name, $reference->itemKey, $language)];
    }

    /** The item's position, counted from 1, as the editor shows it. */
    private function itemNumber(Page $page, string $field, string $key): int
    {
        foreach (Value::list($page->getUnformatted($field)) as $index => $item) {
            if ((Value::array($item)['key'] ?? null) === $key) {
                return $index + 1;
            }
        }

        return 0;
    }
}
