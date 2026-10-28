<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use LynxCms\Contracts\Fields\FieldType;
use LynxCms\Core\Fields\UseSettings;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * A field as used inside an item field: the field itself, plus what the item field's settings say
 * about it here (label, description, notes, required, width).
 *
 * @api
 */
final readonly class Subfield
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function __construct(public Field $field, public array $entry = []) {}

    /**
     * The field's settings in this item: its own, with the ones the block type or repeater sets
     * for it, such as a page field's "max" (ADR-0094).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return UseSettings::of($this->field, $this->entry);
    }

    public function name(): string
    {
        return $this->field->name;
    }

    public function type(): FieldType
    {
        return $this->field->fieldType();
    }

    public function isTranslatable(): bool
    {
        return $this->field->isTranslatable();
    }

    public function isRequired(): bool
    {
        return (bool) ($this->entry['required'] ?? false);
    }

    public function labels(): LocalizedText
    {
        $own = LocalizedText::from($this->entry['label'] ?? null);

        return $own->isEmpty() ? $this->field->labels() : $own;
    }

    public function label(): string
    {
        return $this->labels()->in();
    }

    public function description(): string
    {
        $own = LocalizedText::from($this->entry['description'] ?? null);

        return $own->isEmpty() ? $this->field->description() : $own->in();
    }

    public function notes(): string
    {
        $own = LocalizedText::from($this->entry['notes'] ?? null);

        return $own->isEmpty() ? $this->field->notes() : $own->in();
    }

    /** Share of the row, in percent (100 = full width). */
    public function width(): int
    {
        return max(10, min(100, Value::int($this->entry['width'] ?? 100, 100)));
    }
}
