<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Closure;
use LynxCms\Core\Fields\CarriesFiles;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Support\Value;

/**
 * Files named by subfields of repeater and matrix items (ADR-0050): each subfield whose type
 * carries files answers for its own value, in every language for translatable ones.
 *
 * @internal
 */
final readonly class ItemFiles
{
    public function __construct(private FieldRegistry $fields) {}

    /**
     * @return list<string>
     */
    public function files(mixed $value): array
    {
        $files = [];

        $this->walk($value, function (mixed $subValue, CarriesFiles $type, array $config) use (&$files): mixed {
            array_push($files, ...$type->files($subValue, $config));

            return $subValue;
        });

        return $files;
    }

    /**
     * @param  Closure(string): string  $map
     * @return list<array<string, mixed>>
     */
    public function mapFiles(mixed $value, Closure $map): array
    {
        return $this->walk($value, fn (mixed $subValue, CarriesFiles $type, array $config): mixed => $type->mapFiles($subValue, $map, $config));
    }

    /**
     * Every item with each file-carrying subfield value passed through $visit.
     *
     * @param  Closure(mixed, CarriesFiles, array<string, mixed>): mixed  $visit
     * @return list<array<string, mixed>>
     */
    private function walk(mixed $value, Closure $visit): array
    {
        $items = [];

        foreach (Value::list($value) as $item) {
            $item = Value::map($item);
            $values = Value::map($item['values'] ?? []);

            foreach ($values as $name => $subValue) {
                $field = $this->fields->find($name);
                $type = $field?->fieldType();

                if ($field !== null && $type instanceof CarriesFiles) {
                    $values[$name] = $field->isTranslatable()
                        ? array_map(fn (mixed $v): mixed => $visit($v, $type, $field->config()), Value::map($subValue))
                        : $visit($subValue, $type, $field->config());
                }
            }

            $items[] = ['values' => $values] + $item;
        }

        return $items;
    }
}
