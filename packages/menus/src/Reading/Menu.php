<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

/**
 * A menu resolved for the current language: what a Blade view or a JSON API needs.
 *
 * @api
 */
final readonly class Menu
{
    /**
     * @param  list<MenuItem>  $items
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $title,
        public ?string $class,
        public array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'class' => $this->class,
            'items' => array_map(fn (MenuItem $item): array => $item->toArray(), $this->items),
        ];
    }
}
