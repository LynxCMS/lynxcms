<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;

/** @internal */
final class ToggleConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return $value === true;
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        return (bool) $value;
    }
}
