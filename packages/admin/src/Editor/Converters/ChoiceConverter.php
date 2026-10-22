<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Choices: one value in the form (a select or radios), or a list with "multiple" (checkboxes or a
 * multiple select). The choice type checks the values against the options.
 *
 * @internal
 */
final class ChoiceConverter implements FormConverter, PreparesDisplay
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        $values = Value::strings(is_array($value) ? $value : []);

        return $this->isMultiple($field) ? $values : ($values[0] ?? '');
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        return array_values(array_filter(Value::strings(is_array($value) ? $value : [$value]), fn (string $item): bool => trim($item) !== ''));
    }

    public function display(mixed $value): mixed
    {
        return implode(', ', Value::strings(is_array($value) ? $value : []));
    }

    private function isMultiple(?Field $field): bool
    {
        return ($field?->config()['multiple'] ?? false) === true;
    }
}
