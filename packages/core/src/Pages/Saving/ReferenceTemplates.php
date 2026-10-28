<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;

/**
 * A page field's "templates", checked on every save (ADR-0095): the pages a save adds must use one
 * of the templates its use allows (the field's, or the template's or block type's own, ADR-0094).
 * Pages the field already holds are kept, so narrowing the setting never blocks an unrelated save.
 * Inside repeater and matrix items, each page subfield is checked with its own settings.
 *
 * @internal
 */
final readonly class ReferenceTemplates
{
    public function __construct(private PageLoader $loader) {}

    /**
     * @param  array<string, mixed>  $settings  the field's settings where it is used
     * @return array<string, string> errors keyed under $key
     */
    public function errors(?int $pageId, Field $field, array $settings, mixed $value, string $key): array
    {
        $type = $field->fieldType();

        if (! $type instanceof ReferencesPages || ! $this->limitsTemplates($type, $settings)) {
            return [];
        }

        $stored = $pageId === null ? [] : $this->loader->fieldValues($field->name, [$pageId])[$pageId] ?? [];

        if ($type instanceof ItemFieldType) {
            return $this->itemErrors($type, $settings, $value, $stored, $key);
        }

        $held = [];

        foreach ($stored as $one) {
            array_push($held, ...$type->pageIds($one, $settings));
        }

        $problem = $this->problem(array_diff($type->pageIds($value, $settings), $held), Value::strings($settings['templates'] ?? []));

        return $problem === null ? [] : [$key => $problem];
    }

    /**
     * Whether the use, or for items one of their subfields, allows only some templates: otherwise
     * there is nothing to check and the stored value isn't read.
     *
     * @param  array<string, mixed>  $settings
     */
    private function limitsTemplates(ReferencesPages $type, array $settings): bool
    {
        if (! $type instanceof ItemFieldType) {
            return Value::strings($settings['templates'] ?? []) !== [];
        }

        return array_any($type->allSubfields($settings), fn (Subfield $subfield): bool => Value::strings($subfield->config()['templates'] ?? []) !== []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, mixed>  $stored  language id => the items stored
     * @return array<string, string>
     */
    private function itemErrors(ItemFieldType $type, array $settings, mixed $value, array $stored, string $key): array
    {
        $errors = [];
        $storedItems = [];

        foreach ($stored as $items) {
            array_push($storedItems, ...Value::list($items));
        }

        foreach (Value::list($value) as $index => $item) {
            $item = Value::array($item);

            foreach ($type->subfields($settings, Value::nullableString($item['type'] ?? null)) as $subfield) {
                $subType = $subfield->type();
                $templates = Value::strings($subfield->config()['templates'] ?? []);

                if (! $subType instanceof ReferencesPages || $subType instanceof ItemFieldType || $templates === []) {
                    continue;
                }

                $name = $subfield->name();
                $held = [];

                foreach ($storedItems as $storedItem) {
                    array_push($held, ...$subType->pageIds(Value::array(Value::array($storedItem)['values'] ?? [])[$name] ?? null, $subfield->config()));
                }

                $problem = $this->problem(array_diff($subType->pageIds(Value::array($item['values'] ?? [])[$name] ?? null, $subfield->config()), $held), $templates);

                if ($problem !== null) {
                    $errors["{$key}.{$index}.{$name}"] = $problem;
                }
            }
        }

        return $errors;
    }

    /**
     * Why the added pages can't be held, or null.
     *
     * @param  array<int|string, int>  $added
     * @param  list<string>  $templates  empty allows any
     */
    private function problem(array $added, array $templates): ?string
    {
        if ($templates === [] || $added === []) {
            return null;
        }

        foreach ($this->loader->many(array_values(array_unique($added))) as $page) {
            if (! in_array($page->template()->name, $templates, true)) {
                $name = $page->name() === '' ? '/' : $page->name();

                return 'Pick pages that use '.implode(' or ', $templates).": [{$name}] uses {$page->template()->name}.";
            }
        }

        return null;
    }
}
