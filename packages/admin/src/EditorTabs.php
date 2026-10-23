<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Tabs modules add to the page editor, after the template's tabs and before Settings, such as
 * "Cache" on pages the page cache may store. A tab renders a Livewire component that receives the
 * page's id as pageId. Modules register them in a service provider:
 * app(EditorTabs::class)->add('cache', 'Cache', 'lynx-cache.page-tab', fn (Page $page): bool => ...).
 *
 * @api
 */
final class EditorTabs
{
    /** The editor's own sections, which a module tab never replaces. */
    private const array RESERVED = ['content', 'texts', 'settings'];

    /** @var array<string, array{label: string, component: string, visible: Closure(Page): bool}> */
    private array $tabs = [];

    /**
     * @param  string  $key  the tab's value in the URL (?tab=cache)
     * @param  string  $component  a registered Livewire component name
     * @param  Closure(Page): bool  $visible  whether the page shows the tab, for the current user
     */
    public function add(string $key, string $label, string $component, Closure $visible): void
    {
        $this->tabs[$key] = ['label' => $label, 'component' => $component, 'visible' => $visible];
    }

    /**
     * The tabs the page shows, in the order they were added.
     *
     * @return array<string, array{label: string, component: string}> key => tab
     */
    public function for(Page $page): array
    {
        $tabs = [];

        foreach ($this->tabs as $key => $tab) {
            if (! in_array($key, self::RESERVED, true) && ($tab['visible'])($page)) {
                $tabs[$key] = ['label' => $tab['label'], 'component' => $tab['component']];
            }
        }

        return $tabs;
    }
}
