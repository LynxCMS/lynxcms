<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * A field type whose settings a template, or a block type of a matrix, may set for its own use of
 * the field (ADR-0094), such as a page field's "max": one field holds 4 pages in one place and 8
 * in another. UseSettings merges and checks them.
 *
 * @api
 */
interface SettingsPerUse
{
    /**
     * Setting names a use may set, from the type's own settings.
     *
     * @return list<string>
     */
    public function settingsPerUse(): array;
}
