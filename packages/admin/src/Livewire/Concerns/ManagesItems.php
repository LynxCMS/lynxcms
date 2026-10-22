<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Support\Str;
use LynxCms\Admin\Editor\Converters\ItemsConverter;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * The page editor's repeater and matrix inputs (ADR-0044): add, clone, switch on and off, indent,
 * remove and sort items in the form. Nothing is stored until Save; Save sends the items through
 * pages.save like any other change.
 *
 * @internal
 */
trait ManagesItems
{
    public function addItem(string $field, ?string $type = null): void
    {
        $definition = $this->itemField($field);
        $fieldType = $definition->fieldType();

        if ($fieldType instanceof MatrixType && ! in_array($type, $fieldType->slugs($definition->config()), true)) {
            return;
        }

        $items = $this->items($field);
        $blank = Value::array(Value::list(app(ItemsConverter::class)->toForm([['key' => '', 'type' => $type, 'values' => []]], $definition))[0] ?? []);
        $items[] = ['key' => (string) Str::ulid(), 'new' => true, 'type' => $fieldType instanceof MatrixType ? $type : null, 'enabled' => true, 'depth' => 0,
            'values' => Value::array($blank['values'] ?? [])];
        $this->setItems($field, $items);
    }

    public function cloneItem(string $field, int $index): void
    {
        $items = $this->items($field);

        if (isset($items[$index])) {
            $copy = Value::array($items[$index]);
            $copy['key'] = (string) Str::ulid();
            $copy['new'] = true;
            array_splice($items, $index + 1, 0, [$copy]);
            $this->setItems($field, $items);
        }
    }

    public function toggleItem(string $field, int $index): void
    {
        $this->updateItem($field, $index, fn (array $item): array => ['enabled' => ! (bool) ($item['enabled'] ?? true)] + $item);
    }

    public function indentItem(string $field, int $index, int $by): void
    {
        $max = Value::int($this->itemField($field)->config()['max_depth'] ?? 0);
        $this->updateItem($field, $index, fn (array $item): array => ['depth' => max(0, min($max, Value::int($item['depth'] ?? 0) + $by))] + $item);
    }

    public function removeItem(string $field, int $index): void
    {
        $items = $this->items($field);
        unset($items[$index]);
        $this->setItems($field, array_values($items));
    }

    /** wire:sort handler: "{field}:{item key}" dropped at a 0-based position. */
    public function sortItem(string $item, int|string $position): void
    {
        [$field, $key] = array_pad(explode(':', $item, 2), 2, '');
        $items = $this->items($field);
        $moving = array_values(array_filter($items, fn (mixed $i): bool => Value::array($i)['key'] === $key));

        if ($moving === []) {
            return;
        }

        $rest = array_values(array_filter($items, fn (mixed $i): bool => Value::array($i)['key'] !== $key));
        array_splice($rest, max(0, min(count($rest), Value::int($position))), 0, $moving);
        $this->setItems($field, $rest);
    }

    /**
     * @param  callable(array<array-key, mixed>): array<array-key, mixed>  $change
     */
    private function updateItem(string $field, int $index, callable $change): void
    {
        $items = $this->items($field);

        if (isset($items[$index])) {
            $items[$index] = $change(Value::array($items[$index]));
            $this->setItems($field, $items);
        }
    }

    /**
     * @return list<mixed>
     */
    private function items(string $field): array
    {
        $this->itemField($field);

        return Value::list($this->values[$field][$this->itemCode()] ?? []);
    }

    /**
     * @param  list<mixed>  $items
     */
    private function setItems(string $field, array $items): void
    {
        $this->values[$field][$this->itemCode()] = $items;
        $this->isReviewing = false;
    }

    /** Item fields are shared: their form value sits under the default language. */
    private function itemCode(): string
    {
        return Value::string(lynx()->languages->default()->code);
    }

    /** The field must be an item field of this page's template; the browser picks only its name. */
    private function itemField(string $name): Field
    {
        $templateField = lynx()->templates->fields($this->page()->template()->name)->get($name);
        $field = $templateField?->field;

        abort_unless($field instanceof Field && $field->fieldType() instanceof ItemFieldType, 404);

        return $field;
    }
}
