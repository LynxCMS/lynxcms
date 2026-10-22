<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * A shared field type whose input may carry values for one language, such as a repeater with
 * translatable subfields (ADR-0044): pages.save merges the input into the stored value, so saving
 * one language keeps the others.
 *
 * @api
 */
interface MergesInput
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function merge(mixed $stored, mixed $input, string $languageCode, array $settings): mixed;
}
