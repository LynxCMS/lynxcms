<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Templates\FamilyRules;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The templates a new parent may use for the pages being moved: each must take every one of
 * them under the family rules (ADR-0032), so the page search offers only parents that can.
 * None when no page but the excluded ones (the pages and their current parent) uses them.
 *
 * @internal
 */
final readonly class ParentTemplates
{
    public function __construct(private TemplateRegistry $templates, private FamilyRules $family) {}

    /**
     * @param  iterable<Page>  $pages
     * @param  list<int>  $exclude  pages that cannot be the new parent
     * @return list<string>
     */
    public function for(iterable $pages, array $exclude = []): array
    {
        $children = [];

        foreach ($pages as $page) {
            $children[$page->template()->name] = $page->template();
        }

        $names = array_values($this->templates->all()
            ->filter(fn (Template $parent): bool => array_all($children, fn (Template $child): bool => $this->family->allows($parent, $child)))
            ->map(fn (Template $parent): string => (string) $parent->name)
            ->all());

        return $names !== [] && $this->isUsed($names, $exclude) ? $names : [];
    }

    /**
     * @param  list<string>  $names
     * @param  list<int>  $exclude
     */
    private function isUsed(array $names, array $exclude): bool
    {
        $trash = lynx()->pages->trashId();
        $selector = implode(', ', array_filter([
            'template='.implode('|', $names),
            'include=all',
            'limit=1',
            $exclude === [] ? '' : 'id!='.implode('|', $exclude),
            $trash === null ? '' : "has_parent!={$trash}",
        ]));

        return lynx()->pages->find($selector)->total() > 0;
    }
}
