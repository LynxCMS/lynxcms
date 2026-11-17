<?php

declare(strict_types=1);

namespace LynxCms\Admin\Home;

use Closure;
use InvalidArgumentException;
use LynxCms\Core\Support\Value;

/**
 * What is on the admin's home screen: stats in the row at the top, buttons for common actions
 * beside the welcome, and panels in a main and a side column, Lynx's own lists among them
 * (ADR-0123). Each entry has a key and shows to the users its closure accepts. Register them in a
 * service provider:
 * app(HomeScreen::class)->stat('reports', 'Reports', fn (): int => Report::count(), url: route('lynx.admin.reports')).
 *
 * remove() and move() change any entry by its key, whoever added it and whenever: they apply when
 * the screen is drawn, so a site's provider can arrange what modules register after it.
 *
 * @api
 */
final class HomeScreen
{
    public const string MAIN = 'main';

    public const string SIDE = 'side';

    private const array COLUMNS = [self::MAIN, self::SIDE];

    /** @var list<array{key: string, view: string, column: string, visible: (Closure(): bool)|null, order: int}> */
    private array $panels = [];

    /** @var list<array{key: string, label: string, value: Closure(): (int|string|null), url: (Closure(): ?string)|string|null, icon: string|null, visible: (Closure(): bool)|null, order: int}> */
    private array $stats = [];

    /** @var list<array{key: string, label: string, url: (Closure(): string)|string, icon: string|null, visible: (Closure(): bool)|null, order: int}> */
    private array $actions = [];

    /** @var array<string, true> keys of entries left out */
    private array $removed = [];

    /** @var array<string, array{order: int, column: string|null}> new places by key */
    private array $moves = [];

    /**
     * A Blade view in one of the columns; give it its data with a view composer.
     *
     * @param  (Closure(): bool)|null  $visible  who sees it; everyone who opens the admin when null
     * @param  int  $order  lower comes first; Lynx's own lists use 10 and 20
     * @param  string|null  $key  for remove() and move(); the view's name when null
     * @param  string  $column  self::MAIN or self::SIDE
     */
    public function panel(string $view, ?Closure $visible = null, int $order = 50, ?string $key = null, string $column = self::MAIN): void
    {
        $this->panels[] = ['key' => $key ?? $view, 'view' => $view, 'column' => $this->column($column), 'visible' => $visible, 'order' => $order];
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

    /** Leaves out the stats, actions and panels with this key; a key nothing has changes nothing. */
    public function remove(string $key): void
    {
        $this->removed[$key] = true;
    }

    /**
     * Gives the entries with this key a new order and, for a panel, another column.
     *
     * @param  string|null  $column  self::MAIN or self::SIDE; the panel's own when null
     */
    public function move(string $key, int $order, ?string $column = null): void
    {
        $this->moves[$key] = ['order' => $order, 'column' => $column === null ? null : $this->column($column)];
    }

    /** @return list<string> the views of the panels the current user sees in the column, in order */
    public function panels(string $column = self::MAIN): array
    {
        return array_column($this->panelsIn($column), 'view');
    }

    /** @return list<string> the keys of the panels the current user sees in the column, in order */
    public function panelKeys(string $column = self::MAIN): array
    {
        return array_column($this->panelsIn($column), 'key');
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

    /** @return list<array{key: string, view: string, column: string, visible: (Closure(): bool)|null, order: int}> */
    private function panelsIn(string $column): array
    {
        $column = $this->column($column);
        $panels = array_map(fn (array $panel): array => ['column' => $this->moves[$panel['key']]['column'] ?? $panel['column']] + $panel, $this->panels);

        return $this->shown(array_values(array_filter($panels, fn (array $panel): bool => $panel['column'] === $column)));
    }

    /**
     * @template T of array{key: string, visible: (Closure(): bool)|null, order: int}
     *
     * @param  list<T>  $entries
     * @return list<T> those not removed that the current user sees, by order, then in the order they were added
     */
    private function shown(array $entries): array
    {
        $shown = array_values(array_filter($entries, fn (array $entry): bool => ! isset($this->removed[$entry['key']]) && ($entry['visible'] === null || ($entry['visible'])())));
        usort($shown, fn (array $a, array $b): int => $this->orderOf($a) <=> $this->orderOf($b));

        return $shown;
    }

    /** @param  array{key: string, order: int}  $entry */
    private function orderOf(array $entry): int
    {
        return $this->moves[$entry['key']]['order'] ?? $entry['order'];
    }

    private function column(string $column): string
    {
        if (! in_array($column, self::COLUMNS, true)) {
            throw new InvalidArgumentException("The home screen has no [{$column}] column; use HomeScreen::MAIN or HomeScreen::SIDE.");
        }

        return $column;
    }

    /** A link given as a closure runs now, so it can read the current user or the routes. */
    private function url(Closure|string|null $url): ?string
    {
        return $url instanceof Closure ? Value::nullableString($url()) : $url;
    }
}
