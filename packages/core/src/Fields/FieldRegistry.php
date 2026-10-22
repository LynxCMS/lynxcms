<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Flushable;

/**
 * Read access to field definitions, cached per request.
 *
 * @api
 */
final class FieldRegistry implements Flushable
{
    /** @var Collection<string, Field>|null */
    private ?Collection $fields = null;

    /**
     * @return Collection<string, Field>
     */
    public function all(): Collection
    {
        return $this->fields ??= Field::query()->orderBy('name')->get()->keyBy('name');
    }

    public function find(Field|int|string|null $reference): ?Field
    {
        return match (true) {
            $reference === null => null,
            $reference instanceof Field => $this->all()->get($reference->name),
            is_int($reference) => $this->all()->first(fn (Field $f): bool => $f->id === $reference),
            default => $this->all()->get($reference) ?? $this->all()->first(fn (Field $f): bool => $f->uuid === $reference),
        };
    }

    public function get(Field|int|string $reference): Field
    {
        return $this->find($reference) ?? throw new InvalidArgumentException('Field ['.(is_scalar($reference) ? $reference : $reference->name).'] does not exist.');
    }

    /**
     * @return Collection<string, Field>
     */
    public function translatable(): Collection
    {
        return $this->all()->filter(fn (Field $f): bool => $f->isTranslatable());
    }

    /**
     * Repeater and matrix fields that show the field in their items (ADR-0044).
     *
     * @return list<string> their names, sorted
     */
    public function itemFieldsUsing(string $name): array
    {
        return array_values($this->all()
            ->filter(function (Field $field) use ($name): bool {
                $type = $field->fieldType();

                return $type instanceof ItemFieldType
                    && in_array($name, array_map(fn (Subfield $subfield): string => $subfield->name(), $type->allSubfields($field->config())), true);
            })
            ->keys()
            ->map(fn (int|string $key): string => (string) $key)
            ->all());
    }

    public function flush(): void
    {
        $this->fields = null;
    }
}
