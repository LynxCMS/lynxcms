<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Models\Field;

/**
 * A field type that turns its stored (woken) value into what templates read, such as a list of
 * pages or an image object (ADR-0042). Page::get() calls it for shared fields; translatable
 * fields stay TranslatableValue. getUnformatted() always returns the stored value.
 *
 * @api
 */
interface PresentsValue
{
    public function present(mixed $value, Field $field): mixed;
}
