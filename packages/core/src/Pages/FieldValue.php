<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use LynxCms\Core\Models\Field;
use Stringable;

/**
 * The value of a text field as templates see it (ADR-0031): {{ $page->body }} prints toHtml(), the
 * value formatted for output; value(), string casts and JSON give the stored value.
 *
 * @api
 */
interface FieldValue extends Htmlable, JsonSerializable, Stringable
{
    /** The stored value, unformatted. */
    public function value(): mixed;

    public function isEmpty(): bool;

    public function field(): Field;
}
