<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\LayoutRules;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;

/**
 * The layout builder of the Templates screen: tabs, each with a main and a sidebar list of
 * fields and sections, edited by drag and drop (one wire:sort group) or the Place select. Saved
 * with the field list in one templates.update (TemplateForm::saveFields).
 *
 * Items are "field:{name}" or "section:{key}"; lists are "tab:{tab}:{column}" or "section:{key}".
 */
trait EditsTemplateLayout
{
    /** @var list<array{key: string, label: array<string, string>}> the template's own tabs */
    public array $tabs = [];

    /** @var array<string, array{label: array<string, string>, collapsed: bool}> */
    public array $sections = [];

    /** @var array<string, array<string, list<string>>> tab => column => items */
    public array $board = [];

    /** @var array<string, list<string>> section key => its field names */
    public array $sectionFields = [];

    /** @var array<string, string> language code => label of the tab to add */
    public array $newTab = [];

    /** @var array<string, string> language code => label of the section to add */
    public array $newSection = [];

    /** wire:sort handler: an item dropped at a 0-based position of a list. Sections do not nest. */
    public function moveItem(string $item, int|string $position, string $target): void
    {
        if (! $this->hasItem($item) || ! $this->isList($target) || (str_starts_with($item, 'section:') && str_starts_with($target, 'section:'))) {
            return;
        }

        $this->detach($item);
        $position = max(0, Value::int($position));

        if (str_starts_with($target, 'section:')) {
            array_splice($this->sectionFields[substr($target, 8)], $position, 0, [substr($item, 6)]);
        } else {
            [, $tab, $column] = explode(':', $target, 3);
            array_splice($this->board[$tab][$column], $position, 0, [$item]);
        }

        $this->syncOrder();
    }

    /** The Place select: the item moves to the end of the chosen list (the keyboard way to drag). */
    public function placeItem(string $item, string $target): void
    {
        $count = str_starts_with($target, 'section:')
            ? count($this->sectionFields[substr($target, 8)] ?? [])
            : count($this->listAt($target) ?? []);

        $this->moveItem($item, $count, $target);
    }

    public function addTab(): void
    {
        $label = LocalizedForm::fromForm($this->newTab);
        $key = $this->newKey($label, [...LayoutRules::RESERVED_TABS, ...array_column($this->tabs, 'key')]);
        $this->tabs[] = ['key' => $key, 'label' => LocalizedForm::toForm($label)];
        $this->board[$key] = [TemplateLayout::MAIN => [], TemplateLayout::SIDEBAR => []];
        $this->newTab = LocalizedForm::toForm(null);
    }

    /** A removed tab's fields and sections move to the end of Content. */
    public function removeTab(string $key): void
    {
        foreach ($this->board[$key] ?? [] as $items) {
            array_push($this->board[LayoutTab::CONTENT][TemplateLayout::MAIN], ...$items);
        }

        unset($this->board[$key]);
        $this->tabs = array_values(array_filter($this->tabs, fn (array $tab): bool => $tab['key'] !== $key));
        $this->syncOrder();
    }

    public function addSection(): void
    {
        $label = LocalizedForm::fromForm($this->newSection);
        $key = $this->newKey($label, array_keys($this->sections));
        $this->sections[$key] = ['label' => LocalizedForm::toForm($label), 'collapsed' => false];
        $this->sectionFields[$key] = [];
        $this->board[LayoutTab::CONTENT][TemplateLayout::MAIN][] = "section:{$key}";
        $this->newSection = LocalizedForm::toForm(null);
    }

    /** A removed section's fields stay where the section was. */
    public function removeSection(string $key): void
    {
        foreach ($this->board as $tab => $columns) {
            foreach ($columns as $column => $items) {
                $index = array_search("section:{$key}", $items, true);

                if ($index !== false) {
                    array_splice($this->board[$tab][$column], $index, 1, array_map(fn (string $name): string => "field:{$name}", $this->sectionFields[$key] ?? []));
                }
            }
        }

        unset($this->sections[$key], $this->sectionFields[$key]);
        $this->syncOrder();
    }

    private function loadLayout(Template $template): void
    {
        $layout = TemplateLayout::for($template);
        $this->tabs = [];
        $this->sections = [];
        $this->sectionFields = [];
        $this->board = [];

        foreach ($layout->tabs() as $tab) {
            if ($tab->key !== LayoutTab::CONTENT) {
                $this->tabs[] = ['key' => $tab->key, 'label' => LocalizedForm::toForm($tab->label)];
            }

            foreach (TemplateLayout::COLUMNS as $column) {
                $this->board[$tab->key][$column] = [];

                foreach ($layout->blocks($tab->key, $column) as $block) {
                    if ($block->section === null) {
                        $this->board[$tab->key][$column][] = 'field:'.$block->field;

                        continue;
                    }

                    $key = $block->section->key;
                    $this->sections[$key] = ['label' => LocalizedForm::toForm($block->section->label), 'collapsed' => $block->section->collapsed];
                    $this->sectionFields[$key] = $block->fields;
                    $this->board[$tab->key][$column][] = "section:{$key}";
                }
            }
        }

        $this->newTab = LocalizedForm::toForm(null);
        $this->newSection = LocalizedForm::toForm(null);
    }

    /** A field added to the template starts at the end of Content. */
    private function placeNewField(string $name): void
    {
        $this->board[LayoutTab::CONTENT][TemplateLayout::MAIN][] = "field:{$name}";
    }

    private function forgetField(string $name): void
    {
        $this->detach("field:{$name}");
    }

    /**
     * The field list for templates.update: fields and nested sections, tab by tab.
     *
     * @param  callable(string): array<string, mixed>  $settingsOf  a field's own overrides
     * @return list<mixed>
     */
    private function layoutFieldList(callable $settingsOf): array
    {
        $spec = fn (string $name, array $place = []): array|string => ($settings = $settingsOf($name) + $place) === [] ? $name : ['name' => $name] + $settings;
        $list = [];

        foreach ($this->board as $tab => $columns) {
            foreach ($columns as $column => $items) {
                $place = array_filter(['tab' => $tab === LayoutTab::CONTENT ? null : $tab, 'column' => $column === TemplateLayout::MAIN ? null : $column]);

                foreach ($items as $item) {
                    [$kind, $name] = explode(':', $item, 2);
                    $list[] = $kind === 'field' ? $spec($name, $place) : ['section' => $name, 'label' => LocalizedForm::fromForm($this->sections[$name]['label']) ?? $name]
                        + $place + array_filter(['collapsed' => $this->sections[$name]['collapsed'] ?? false])
                        + ['fields' => array_map(fn (string $field): array|string => $spec($field), $this->sectionFields[$name] ?? [])];
                }
            }
        }

        return $list;
    }

    /**
     * @return list<array{key: string, label: string|array<string, string>}>
     */
    private function layoutTabs(): array
    {
        return array_map(fn (array $tab): array => ['key' => $tab['key'], 'label' => LocalizedForm::fromForm($tab['label']) ?? $tab['key']], $this->tabs);
    }

    /** The field order follows the board: tab by tab, main then sidebar, sections in place. */
    private function syncOrder(): void
    {
        $order = [];

        foreach ($this->board as $columns) {
            foreach ($columns as $items) {
                foreach ($items as $item) {
                    [$kind, $name] = explode(':', $item, 2);
                    array_push($order, ...($kind === 'field' ? [$name] : $this->sectionFields[$name] ?? []));
                }
            }
        }

        $this->order = $order;
    }

    private function detach(string $item): void
    {
        foreach ($this->board as $tab => $columns) {
            foreach ($columns as $column => $items) {
                $this->board[$tab][$column] = array_values(array_diff($items, [$item]));
            }
        }

        if (str_starts_with($item, 'field:')) {
            foreach ($this->sectionFields as $key => $fields) {
                $this->sectionFields[$key] = array_values(array_diff($fields, [substr($item, 6)]));
            }
        }
    }

    private function hasItem(string $item): bool
    {
        return str_starts_with($item, 'field:') ? in_array(substr($item, 6), $this->order, true) : isset($this->sections[substr($item, 8)]);
    }

    private function isList(string $target): bool
    {
        return str_starts_with($target, 'section:') ? isset($this->sections[substr($target, 8)]) : $this->listAt($target) !== null;
    }

    /**
     * @return list<string>|null
     */
    private function listAt(string $target): ?array
    {
        $parts = explode(':', $target, 3);

        return $parts[0] === 'tab' && count($parts) === 3 ? ($this->board[$parts[1]][$parts[2]] ?? null) : null;
    }

    /**
     * @param  string|array<string, string>|null  $label
     * @param  list<string>  $taken
     */
    private function newKey(string|array|null $label, array $taken): string
    {
        $text = is_array($label) ? (string) (reset($label) ?: '') : (string) $label;
        $key = Str::slug($text, '_');

        if (preg_match('/^[a-z]/', $key) !== 1) {
            throw ValidationException::withMessages(['layout' => __('lynx-admin::templates.layout.needs_letter')]);
        }

        $base = $key;

        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }
}
