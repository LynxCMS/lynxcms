<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Fields\Formatters\HeadingAnchors;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Pages, sections and files the link dialog offers, in the language of the text being edited:
 * pages found by words or browsed in the tree, the headings of a page's text fields that carry
 * the heading_anchors formatter, and the files of a page (LinkFiles).
 *
 * @internal
 */
final readonly class LinkPages
{
    private const int SEARCH_LIMIT = 8;

    private const int BROWSE_LIMIT = 100;

    public function __construct(private PageSearch $search, private LinkFiles $files) {}

    /**
     * Linkable pages matching the words.
     *
     * @return list<array{id: int, title: string, url: string, canHaveChildren: bool}>
     */
    public function search(string $words, string $language): array
    {
        return array_values(array_filter($this->search->find(trim($words), self::SEARCH_LIMIT)->map(fn (Page $page): ?array => $this->row($page, $language))));
    }

    /**
     * The page being browsed (the home page when null), its parent and its children.
     *
     * @return array{page: array{id: int, title: string, url: string, canHaveChildren: bool}, parent: int|null, children: list<array{id: int, title: string, url: string, canHaveChildren: bool}>}|null
     */
    public function browse(?int $id, string $language): ?array
    {
        // The home page is where browsing starts for everyone, as the way to the pages below it.
        $page = $id === null ? lynx()->pages->get('/') : $this->viewable($id);

        if (! $page instanceof Page) {
            return null;
        }

        $parent = $page->parent();
        $children = lynx()->pages->find('parent='.$page->id.', include=all, limit='.self::BROWSE_LIMIT, scope: app(AdminAccess::class)->viewScope());

        return [
            'page' => $this->row($page, $language) ?? ['id' => (int) $page->id, 'title' => Value::string($page->title), 'url' => '', 'canHaveChildren' => true],
            'parent' => $this->viewable($parent?->id)?->id,
            'children' => array_values(array_filter($children->map(fn (Page $child): ?array => $this->row($child, $language)))),
        ];
    }

    /**
     * A page that can be linked: its title and address in the language.
     *
     * @return array{id: int, title: string, url: string, canHaveChildren: bool}|null
     */
    public function row(Page $page, string $language): ?array
    {
        if (! $page->template()->hasUrl()) {
            return null;
        }

        return lynx()->language($language, fn (): array => [
            'id' => (int) $page->id,
            'title' => Value::string($page->title),
            'url' => $page->localUrl($language),
            'canHaveChildren' => $page->template()->allowsChildren(),
        ]);
    }

    /**
     * Sections of the page to link to: the headings of its text fields that give headings ids.
     *
     * @return list<array{id: string, label: string}>
     */
    public function anchors(?int $id, string $language): array
    {
        $page = $this->viewable($id);
        $anchors = [];

        if (! $page instanceof Page) {
            return [];
        }

        foreach (lynx()->templates->fields($page->template()) as $name => $templateField) {
            if (in_array(HeadingAnchors::name(), Value::strings($templateField->field->config()['formatters'] ?? []), true)) {
                $html = lynx()->language($language, fn (): mixed => $page->getUnformatted((string) $name));
                array_push($anchors, ...HeadingAnchors::anchors(is_string($html) ? $html : ''));
            }
        }

        return $anchors;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function files(?int $id): array
    {
        $page = $this->viewable($id);

        return $page instanceof Page ? $this->files->for($page) : [];
    }

    /** The page, unless the current user may not view it: ids come from a request a client can change. */
    private function viewable(?int $id): ?Page
    {
        return $id === null || app(AdminAccess::class)->viewable([$id]) === [] ? null : lynx()->pages->get($id);
    }
}
