<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Field;

/**
 * Converts stored values to what form inputs hold and back, through the converter registered with
 * each type's input (FieldInputs, ADR-0042). Types without one pass through unchanged.
 *
 * @internal
 */
final readonly class FormValues
{
    public function __construct(private FieldInputs $inputs) {}

    public function toForm(string $type, mixed $value, ?Field $field = null): mixed
    {
        $converter = $this->inputs->converter($type);

        if (! $converter instanceof FormConverter) {
            return $value;
        }

        return $converter->toForm($value, $field);
    }

    public function fromForm(string $type, mixed $value, ?Field $field = null): mixed
    {
        $converter = $this->inputs->converter($type);

        // A converter may return null on purpose (empty input falls back), so no null coalescing here.
        if (! $converter instanceof FormConverter) {
            return $value;
        }

        return $converter->fromForm($value, $field);
    }
}
