<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Branches of the tree that a module edits on its own screen, like the menus under /_menus/.
 * The site's page tree leaves them out, and the page editor sends their pages to the module's
 * screen instead; when the URL closure returns null, the page stays in the page editor (form
 * pages under /_forms/). Modules register them in a service provider:
 * app(AdminBranches::class)->register('menus', fn (Page $page): string => route(...)).
 *
 * @api
 */
final class AdminBranches
{
    /** @var array<string, Closure(Page, Page): ?string> root template => URL for a page of the branch, given the page and the branch root */
    private array $branches = [];

    /**
     * @param  Closure(Page $page, Page $root): ?string  $editUrl  null keeps the page in the page editor
     */
    public function register(string $rootTemplate, Closure $editUrl): void
    {
        $this->branches[$rootTemplate] = $editUrl;
    }

    /** @return list<string> */
    public function rootTemplates(): array
    {
        return array_keys($this->branches);
    }

    /** Where to edit the page when its branch's module edits it elsewhere, else null. */
    public function editUrlFor(Page $page): ?string
    {
        $branch = $this->branchOf($page);

        return $branch === null ? null : ($this->branches[$branch['template']])($page, $branch['root']);
    }

    /** Whether the page is in a module's branch, edited on the module's screen or not (a form). */
    public function contains(Page $page): bool
    {
        return $this->branchOf($page) !== null;
    }

    /** @return array{template: string, root: Page}|null */
    private function branchOf(Page $page): ?array
    {
        foreach (array_keys($this->branches) as $template) {
            foreach (lynx()->pages->find("template={$template}, include=all") as $root) {
                $isInside = $root->id === $page->id || lynx()->pages->count("id={$page->id}, has_parent={$root->id}, include=all") === 1;

                if ($isInside && $root instanceof Page) {
                    return ['template' => $template, 'root' => $root];
                }
            }
        }

        return null;
    }
}
