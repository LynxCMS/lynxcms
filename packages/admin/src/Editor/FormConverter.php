<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Field;

/**
 * Converts a field type's stored value to what its input holds and back (ADR-0042). Registered
 * with the type's input in FieldInputs; types without one pass values through unchanged.
 *
 * @api
 */
interface FormConverter
{
    /** $field is the field being edited, for converters that depend on its settings. */
    public function toForm(mixed $value, ?Field $field = null): mixed;

    /** What pages.save receives. Empty input becomes null, so the value falls back again. */
    public function fromForm(mixed $value, ?Field $field = null): mixed;
}
