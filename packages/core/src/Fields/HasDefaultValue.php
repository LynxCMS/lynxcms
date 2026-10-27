<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * A field type whose fields can start with a value: pages.save stores it on a new page, and in a
 * new repeater or matrix item, when the input leaves the field out (ADR-0088). Existing content
 * is never filled in, and an explicitly empty value stays empty.
 *
 * @api
 */
interface HasDefaultValue
{
    /**
     * The value new content starts with, in the form pages.save accepts, or null for none.
     *
     * @param  array<string, mixed>  $settings
     */
    public function defaultValue(array $settings): mixed;
}
