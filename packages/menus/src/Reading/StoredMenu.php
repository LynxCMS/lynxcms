<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Support\Value;

/**
 * A built menu as JSON for lynx_menu_builds, and back (ADR-0068). The active trail is not stored:
 * ActiveTrail marks it per request.
 *
 * @internal
 */
final class StoredMenu
{
    public static function encode(Menu $menu): string
    {
        return json_encode(['id' => $menu->id, ...$menu->toArray()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function decode(string $json): ?Menu
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['id'], $data['items'])) {
            return null;
        }

        return new Menu(
            Value::int($data['id']),
            Value::string($data['name'] ?? null),
            Value::string($data['title'] ?? null),
            Value::nullableString($data['class'] ?? null),
            self::items($data['items']),
        );
    }

    /** @return list<MenuItem> */
    private static function items(mixed $items): array
    {
        return array_values(array_map(fn (mixed $item): MenuItem => self::item(Value::map($item)), Value::list($items)));
    }

    /** @param array<string, mixed> $item */
    private static function item(array $item): MenuItem
    {
        $kind = Value::string($item['kind'] ?? null);

        return new MenuItem(
            Value::nullableInt($item['id'] ?? null),
            in_array($kind, ['section', 'page', 'link'], true) ? $kind : 'section',
            Value::string($item['label'] ?? null),
            Value::nullableString($item['url'] ?? null),
            Value::nullableString($item['target'] ?? null),
            Value::nullableString($item['rel'] ?? null),
            Value::nullableString($item['class'] ?? null),
            Value::int($item['depth'] ?? null),
            false,
            false,
            self::items($item['children'] ?? []),
            Value::nullableInt($item['page_id'] ?? null),
            Value::nullableString($item['icon_before'] ?? null),
            Value::nullableString($item['icon_after'] ?? null),
        );
    }
}
