<?php

declare(strict_types=1);

namespace LynxCms\Admin\Icons;

use BladeUI\Icons\Factory;
use LynxCms\Core\Support\Value;

/**
 * The icons the admin can show: the Lucide set's file names ("calendar", "map-pin"). The data
 * source of the icon field type and the icon picker.
 *
 * @api
 */
final class IconSet
{
    public const string SET = 'lucide';

    /** @var list<string>|null */
    private ?array $names = null;

    public function __construct(private readonly Factory $icons) {}

    /** @return list<string> every icon name, sorted */
    public function names(): array
    {
        if ($this->names === null) {
            $names = [];

            foreach (Value::strings(Value::map(Value::map($this->icons->all())[self::SET] ?? [])['paths'] ?? []) as $path) {
                foreach (glob($path.'/*.svg') ?: [] as $file) {
                    $names[] = basename($file, '.svg');
                }
            }

            sort($names);
            $this->names = array_values(array_unique($names));
        }

        return $this->names;
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->names(), true);
    }

    /**
     * Names containing every word of $query, names starting with it first.
     *
     * @return list<string>
     */
    public function search(string $query, int $limit): array
    {
        $words = preg_split('/[\s-]+/', mb_strtolower(trim($query)), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $matches = array_filter($this->names(), fn (string $name): bool => array_all($words, fn (string $word): bool => str_contains($name, $word)));
        $first = $words[0] ?? '';
        usort($matches, fn (string $a, string $b): int => [! str_starts_with($a, $first), $a] <=> [! str_starts_with($b, $first), $b]);

        return array_slice($matches, 0, $limit);
    }
}
