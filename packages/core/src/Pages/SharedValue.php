<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use LynxCms\Core\Fields\FormatterChain;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * The value of a shared (not translatable) text field, so it formats in templates like a
 * translatable one (ADR-0031).
 *
 * @api
 */
final readonly class SharedValue implements FieldValue
{
    public function __construct(private Field $field, private mixed $value) {}

    public function value(): mixed
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '';
    }

    public function field(): Field
    {
        return $this->field;
    }

    public function toHtml(): string
    {
        return app(FormatterChain::class)->render($this->field, $this->value);
    }

    public function __toString(): string
    {
        return Value::string($this->value);
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
