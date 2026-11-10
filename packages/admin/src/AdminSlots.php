<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Livewire components and views modules add to named places in the admin (ADR-0101). The
 * page editor's header renders the "editor.header" slot, mounting each component with the page's
 * id (pageId); each field renders the views of "editor.field", and each editor tab those of
 * AdminSlots::editorTab($key). Modules register them in a service provider:
 * app(AdminSlots::class)->add('editor.header', 'translate-button', fn (Page $page): bool => ...).
 *
 * @api
 */
final class AdminSlots
{
    /** The page editor's header, beside the save buttons; shown to editors who may edit the page. */
    public const string EDITOR_HEADER = 'editor.header';

    /** The Site texts screen's header, beside its title; components get the home page's id. */
    public const string SITE_TEXTS_HEADER = 'site-texts.header';

    /**
     * Under each field of the page editor, in each language: a Blade view given page (Page),
     * field (the field's name), language (the language's code) and isTranslatable (bool).
     */
    public const string EDITOR_FIELD = 'editor.field';

    /**
     * Below the fields of one page editor tab, in its main column: the slot for views given page
     * (Page) and tab (the tab's key), such as the SEO module's preview on the SEO tab.
     */
    public static function editorTab(string $tab): string
    {
        return 'editor.tab.'.$tab;
    }

    /** @var array<string, list<array{component: string, visible: (Closure(Page): bool)|null}>> */
    private array $slots = [];

    /** @var array<string, list<array{view: string, visible: (Closure(Page): bool)|null}>> */
    private array $views = [];

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

    /**
     * A Blade view for a slot that repeats, such as one per field: lighter than a component.
     *
     * @param  (Closure(Page): bool)|null  $visible  which pages show it; every page when null
     */
    public function addView(string $slot, string $view, ?Closure $visible = null): void
    {
        $this->views[$slot][] = ['view' => $view, 'visible' => $visible];
    }

    /**
     * The views the slot shows for the page, in the order they were added.
     *
     * @return list<string>
     */
    public function viewsFor(string $slot, Page $page): array
    {
        $shown = array_filter($this->views[$slot] ?? [], fn (array $entry): bool => $entry['visible'] === null || ($entry['visible'])($page));

        return array_values(array_map(fn (array $entry): string => $entry['view'], $shown));
    }
}
