<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * A field type that cleans its settings when a field is created or updated, after the type's
 * settingsRules() passed, such as filling a choice's empty value from its label.
 *
 * @api
 */
interface NormalizesSettings
{
    /**
     * @param  array<string, mixed>  $settings  the validated settings that were given
     * @return array<string, mixed>
     */
    public function normalizeSettings(array $settings): array;
}
