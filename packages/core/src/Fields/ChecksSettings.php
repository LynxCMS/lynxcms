<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Validation\ValidationException;

/**
 * A field type whose settings depend on each other, such as a choice's default and its options.
 * fields.create and fields.update call it with the complete settings, after merging an update
 * into the stored ones, so changing one setting cannot leave another one invalid.
 *
 * @api
 */
interface ChecksSettings
{
    /**
     * @param  array<string, mixed>  $settings  every setting the field will have
     *
     * @throws ValidationException keyed like the input ("settings.default")
     */
    public function checkSettings(array $settings): void;
}
