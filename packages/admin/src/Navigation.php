<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;

/**
 * Links in the admin's sidebar. Modules add their screens in a service provider:
 * app(Navigation::class)->add('Menus', 'lynx.admin.menus', order: 20, icon: 'menu').
 * Setup screens go in a group, which the sidebar shows as a closed section until opened:
 * ->add('SEO', 'lynx.admin.seo', group: Navigation::SETTINGS). A label can be a translation key
 * ("acme::nav.reports"): it is read when the sidebar shows, in the admin's language (ADR-0097).
 *
 * @phpstan-type Item array{label: string, route: string, order: int, visible: (Closure(): bool)|null, icon: string|null, badge: (Closure(): (int|string|null))|null, group: string|null}
 * @phpstan-type Group array{key: string, label: string, order: int, icon: string|null, items: list<Item>}
 *
 * @api
 */
final class Navigation
{
    /** Fields, templates, languages and the modules' site settings. */
    public const string SETTINGS = 'settings';

    /** Roles and users. */
    public const string ACCESS = 'access';

    /** @var list<Item> */
    private array $items = [];

    /** @var array<string, array{key: string, label: string, order: int, icon: string|null}> */
    private array $groups = [];

    /**
     * $visible: shown only when it returns true, e.g. for screens some users may not open.
     * $icon: a Lucide icon name (https://lucide.dev/icons), e.g. "folder-tree".
     * $badge: a count or short text beside the label, e.g. new form submissions; 0 and null hide it.
     * $group: the key of a group the item belongs to, e.g. Navigation::SETTINGS; an unknown
     * group puts the item outside groups.
     */
    public function add(string $label, string $route, int $order = 100, ?Closure $visible = null, ?string $icon = null, ?Closure $badge = null, ?string $group = null): self
    {
        $this->items[] = ['label' => $label, 'route' => $route, 'order' => $order, 'visible' => $visible, 'icon' => $icon, 'badge' => $badge, 'group' => $group];

        return $this;
    }

    /** A section of the sidebar; $order places it among the items outside groups. */
    public function group(string $key, string $label, int $order = 100, ?string $icon = null): self
    {
        $this->groups[$key] = ['key' => $key, 'label' => $label, 'order' => $order, 'icon' => $icon];

        return $this;
    }

    /**
     * What the sidebar shows, in order: items outside groups, and groups holding the items the
     * current user may see. Groups with none are left out.
     *
     * @return list<Item|Group>
     */
    public function menu(): array
    {
        $entries = [];
        $grouped = [];

        foreach ($this->visible() as $item) {
            if ($item['group'] !== null && isset($this->groups[$item['group']])) {
                $grouped[$item['group']][] = $item;
            } else {
                $entries[] = $item;
            }
        }

        foreach ($grouped as $key => $items) {
            $entries[] = ['label' => $this->translated($this->groups[$key]['label'])] + $this->groups[$key] + ['items' => $this->sorted($items)];
        }

        return $this->sorted($entries);
    }

    /**
     * Items the current user may see, in the order the sidebar shows them.
     *
     * @return list<Item>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->menu() as $entry) {
            array_push($items, ...($entry['items'] ?? [$entry]));
        }

        return $items;
    }

    /** @return list<Item> */
    private function visible(): array
    {
        $items = array_filter($this->items, fn (array $item): bool => $item['visible'] === null || ($item['visible'])() === true);

        return array_values(array_map(fn (array $item): array => ['label' => $this->translated($item['label'])] + $item, $items));
    }

    private function translated(string $label): string
    {
        $text = __($label);

        return is_string($text) ? $text : $label;
    }

    /**
     * @template T of array{label: string, order: int}
     *
     * @param  list<T>  $entries
     * @return list<T>
     */
    private function sorted(array $entries): array
    {
        usort($entries, fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return $entries;
    }
}
