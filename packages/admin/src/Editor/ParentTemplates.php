<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminBranches;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Templates\FamilyRules;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The templates a new parent may use for the pages being moved: each must take every one of
 * them under the family rules (ADR-0032), so the page search offers only parents that can.
 * None when no page but the excluded ones uses them.
 *
 * @internal
 */
final readonly class ParentTemplates
{
    public function __construct(private TemplateRegistry $templates, private FamilyRules $family, private AdminBranches $branches) {}

    /**
     * @param  iterable<Page>  $pages
     * @param  list<int>  $exclude  pages that cannot be the new parent
     * @param  list<int>  $excludeBelow  pages that cannot be the new parent, nor any page below them
     * @return list<string>
     */
    public function for(iterable $pages, array $exclude = [], array $excludeBelow = []): array
    {
        $children = [];

        foreach ($pages as $page) {
            $children[$page->template()->name] = $page->template();
        }

        $names = array_values($this->templates->all()
            ->filter(fn (Template $parent): bool => array_all($children, fn (Template $child): bool => $this->family->allows($parent, $child)))
            ->map(fn (Template $parent): string => (string) $parent->name)
            ->all());

        return $names !== [] && $this->isUsed($names, [...$exclude, ...$excludeBelow], $excludeBelow) ? $names : [];
    }

    /**
     * @param  list<string>  $names
     * @param  list<int>  $exclude
     * @param  list<int>  $excludeBelow
     */
    private function isUsed(array $names, array $exclude, array $excludeBelow): bool
    {
        $trash = lynx()->pages->trashId();
        $selector = implode(', ', array_filter([
            'template='.implode('|', $names),
            'include=all',
            'limit=1',
            $exclude === [] ? '' : 'id!='.implode('|', $exclude),
            $excludeBelow === [] ? '' : 'has_parent!='.implode('|', $excludeBelow),
            $trash === null ? '' : "has_parent!={$trash}",
        ]));

        return lynx()->pages->find($selector)->total() > 0;
    }

    /**
     * The top pages of the branches modules keep for themselves (menus, forms, settings pages):
     * never a parent for a site's pages, nor anything below them.
     *
     * @return list<int>
     */
    public function moduleBranches(): array
    {
        $roots = $this->branches->rootTemplates();

        return $roots === [] ? [] : lynx()->pages->find('template='.implode('|', $roots).', include=all')->ids();
    }
}
