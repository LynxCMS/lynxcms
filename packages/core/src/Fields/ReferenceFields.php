<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Fields\Types\TextareaType;
use LynxCms\Core\Models\Field;

/**
 * Which fields can hold references to pages (ADR-0042, ADR-0145): those whose type references
 * pages, textareas only when they hold rich text, whose links may name a page.
 *
 * @internal
 */
final class ReferenceFields
{
    public static function holds(Field $field): bool
    {
        $type = $field->fieldType();

        return $type instanceof ReferencesPages && (! $type instanceof TextareaType || TextareaType::holdsPageLinks($field->config()));
    }
}
