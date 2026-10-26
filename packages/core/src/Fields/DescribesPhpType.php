<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Models\Field;

/**
 * A field type that tells static analysis what templates read: the PHPDoc type of $page->field
 * for a shared (not translatable) field, as the type presents it, null included when an empty value
 * reads as null. Translatable fields read as a TranslatableValue or null (ADR-0087). Used by
 * lynx:phpstan; types without it read as mixed.
 *
 * @api
 */
interface DescribesPhpType
{
    /** A PHPDoc type with fully qualified class names, e.g. "\Carbon\CarbonImmutable|null". */
    public function phpType(Field $field): string;
}
