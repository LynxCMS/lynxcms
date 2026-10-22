<?php

declare(strict_types=1);

namespace LynxCms\Forms\Livewire\Concerns;

use Illuminate\Support\Str;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\FormField;

/**
 * Rows of group fields (deliverables, people…): added, removed and sorted by the visitor. Each
 * row keeps a "_key" for wire:key; it is not a field, so it is never stored.
 */
trait EditsRows
{
    public function addRow(string $group): void
    {
        $field = $this->definition()->field($group);

        if ($field?->type !== 'group' || count($this->rowsOf($group)) >= $field->maxRows) {
            return;
        }

        $this->data[$group] = [...$this->rowsOf($group), ['_key' => Str::lower(Str::random(8))]];
    }

    public function removeRow(string $group, int $index): void
    {
        $rows = $this->rowsOf($group);
        unset($rows[$index]);
        $this->data[$group] = array_values($rows);
        $this->resetErrorBag();
    }

    /** wire:sort handler: a row ("group:key") dropped at a 0-based position. */
    public function sortRow(string $item, int|string $position): void
    {
        [$group, $key] = array_pad(explode(':', $item, 2), 2, '');
        $rows = $this->rowsOf($group);
        $moving = array_values(array_filter($rows, fn (array $row): bool => ($row['_key'] ?? null) === $key));
        $rest = array_values(array_filter($rows, fn (array $row): bool => ($row['_key'] ?? null) !== $key));
        array_splice($rest, max(0, min(count($rest), Value::int($position))), 0, $moving);
        $this->data[$group] = $rest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsOf(string $group): array
    {
        return array_values(array_map(Value::map(...), Value::list($this->data[$group] ?? [])));
    }

    /** Whether more rows may be added to the group. */
    public function canAddRow(FormField $group): bool
    {
        return count($this->rowsOf($group->name)) < $group->maxRows;
    }
}
