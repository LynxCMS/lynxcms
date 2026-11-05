<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Livewire components modules add to named places in the admin (ADR-0101). The page editor's
 * header renders the "editor.header" slot, mounting each component with the page's id
 * (pageId). Modules register them in a service provider:
 * app(AdminSlots::class)->add('editor.header', 'translate-button', fn (Page $page): bool => ...).
 *
 * @api
 */
final class AdminSlots
{
    /** The page editor's header, beside the save buttons; shown to editors who may edit the page. */
    public const string EDITOR_HEADER = 'editor.header';

    /** @var array<string, list<array{component: string, visible: (Closure(Page): bool)|null}>> */
    private array $slots = [];

    /**
     * @param  string  $component  a registered Livewire component name
     * @param  (Closure(Page): bool)|null  $visible  which pages show it; every page when null
     */
    public function add(string $slot, string $component, ?Closure $visible = null): void
    {
        $this->slots[$slot][] = ['component' => $component, 'visible' => $visible];
    }

    /**
     * The components the slot shows for the page, in the order they were added.
     *
     * @return list<string>
     */
    public function for(string $slot, Page $page): array
    {
        $shown = array_filter($this->slots[$slot] ?? [], fn (array $entry): bool => $entry['visible'] === null || ($entry['visible'])($page));

        return array_values(array_map(fn (array $entry): string => $entry['component'], $shown));
    }
}
