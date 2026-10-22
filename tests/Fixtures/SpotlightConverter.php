<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/** The admin side of SpotlightType: the note is the form value. */
final class SpotlightConverter implements FormConverter, PreparesDisplay
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return Value::string(Value::array($value)['note'] ?? '');
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        return ['note' => Value::string($value), 'pages' => []];
    }

    public function display(mixed $value): mixed
    {
        return strtoupper(Value::string(Value::array($value)['note'] ?? ''));
    }
}
