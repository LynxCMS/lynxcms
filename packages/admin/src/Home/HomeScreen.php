<?php

declare(strict_types=1);

namespace LynxCms\Admin\Home;

use Closure;
use LynxCms\Core\Support\Value;

/**
 * What modules and the host app add to the admin's home screen: stats in the row at the top, buttons
 * for common actions beside the welcome, and panels below Lynx's own lists. Each shows to the users
 * its closure accepts. Register them in a service provider:
 * app(HomeScreen::class)->stat('reports', 'Reports', fn (): int => Report::count(), url: route('lynx.admin.reports')).
 *
 * @api
 */
final class HomeScreen
{
    /** @var list<array{view: string, visible: (Closure(): bool)|null, order: int}> */
    private array $panels = [];

    /** @var list<array{key: string, label: string, value: Closure(): (int|string|null), url: (Closure(): ?string)|string|null, icon: string|null, visible: (Closure(): bool)|null, order: int}> */
    private array $stats = [];

    /** @var list<array{key: string, label: string, url: (Closure(): string)|string, icon: string|null, visible: (Closure(): bool)|null, order: int}> */
    private array $actions = [];

    /**
     * A Blade view below the home screen's lists; give it its data with a view composer.
     *
     * @param  (Closure(): bool)|null  $visible  who sees it; everyone who opens the admin when null
     * @param  int  $order  lower comes first
     */
    public function panel(string $view, ?Closure $visible = null, int $order = 50): void
    {
        $this->panels[] = ['view' => $view, 'visible' => $visible, 'order' => $order];
    }

    /**
     * A number in the row of stats at the top. A null value leaves the stat out.
     *
     * @param  string  $label  words or a translation key
     * @param  Closure(): (int|string|null)  $value
     * @param  (Closure(): ?string)|string|null  $url  where the stat links, if anywhere
     * @param  string|null  $icon  a Lucide icon name
     * @param  (Closure(): bool)|null  $visible  who sees it; everyone when null
     */
    public function stat(string $key, string $label, Closure $value, Closure|string|null $url = null, ?string $icon = null, ?Closure $visible = null, int $order = 50): void
    {
        $this->stats[] = ['key' => $key, 'label' => $label, 'value' => $value, 'url' => $url, 'icon' => $icon, 'visible' => $visible, 'order' => $order];
    }

    /**
     * A button for a common task, beside the welcome.
     *
     * @param  string  $label  words or a translation key
     * @param  (Closure(): string)|string  $url
     * @param  (Closure(): bool)|null  $visible  who sees it; everyone when null
     */
    public function action(string $key, string $label, Closure|string $url, ?string $icon = null, ?Closure $visible = null, int $order = 50): void
    {
        $this->actions[] = ['key' => $key, 'label' => $label, 'url' => $url, 'icon' => $icon, 'visible' => $visible, 'order' => $order];
    }

    /** @return list<string> the panels' views the current user sees, in order */
    public function panels(): array
    {
        return array_column($this->shown($this->panels), 'view');
    }

    /** @return list<array{key: string, label: string, value: int|string, url: string|null, icon: string|null}> */
    public function stats(): array
    {
        $stats = [];

        foreach ($this->shown($this->stats) as $stat) {
            $value = ($stat['value'])();

            if ($value !== null) {
                $stats[] = ['key' => $stat['key'], 'label' => Value::string(__($stat['label'])), 'value' => $value, 'url' => $this->url($stat['url']), 'icon' => $stat['icon']];
            }
        }

        return $stats;
    }

    /** @return list<array{key: string, label: string, url: string, icon: string|null}> */
    public function actions(): array
    {
        return array_map(fn (array $action): array => ['key' => $action['key'], 'label' => Value::string(__($action['label'])), 'url' => Value::string($this->url($action['url'])), 'icon' => $action['icon']], $this->shown($this->actions));
    }

    /**
     * @template T of array{visible: (Closure(): bool)|null, order: int}
     *
     * @param  list<T>  $entries
     * @return list<T> those the current user sees, by order, then in the order they were added
     */
    private function shown(array $entries): array
    {
        $shown = array_values(array_filter($entries, fn (array $entry): bool => $entry['visible'] === null || ($entry['visible'])()));
        usort($shown, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $shown;
    }

    /** A link given as a closure runs now, so it can read the current user or the routes. */
    private function url(Closure|string|null $url): ?string
    {
        return $url instanceof Closure ? Value::nullableString($url()) : $url;
    }
}
