<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\ComponentSettings;
use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Component fields: no component chosen means no value; otherwise the schema's settings, cast.
 *
 * @internal
 */
final readonly class ComponentConverter implements FormConverter
{
    public function __construct(private ComponentSettings $components) {}

    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        $value = Value::array($value);

        return ['component' => Value::string($value['component'] ?? ''), 'settings' => Value::array($value['settings'] ?? [])];
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $value = Value::array($value);
        $alias = Value::string($value['component'] ?? '');

        return $alias === '' ? null : ['component' => $alias, 'settings' => $this->components->cast($alias, Value::array($value['settings'] ?? []))];
    }
}
