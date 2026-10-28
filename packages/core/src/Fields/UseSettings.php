<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Support\Facades\Validator;
use LynxCms\Core\Models\Field;

/**
 * A field's settings where a template or a block type uses it (ADR-0094): the field's own, with
 * the ones the use sets for itself. Only the settings the type lists (SettingsPerUse) may be set;
 * keys of other kinds in the use, such as a label, are not settings and are left to the caller.
 *
 * @internal
 */
final class UseSettings
{
    /** Settings a use may set for some field type; any other key in a use is not a setting. */
    public const array KEYS = ['min', 'max', 'templates'];

    /**
     * @param  array<string, mixed>  $use  the template's field entry, or the block type's subfield entry
     * @return array<string, mixed>
     */
    public static function of(Field $field, array $use): array
    {
        $type = $field->fieldType();
        $own = $type instanceof SettingsPerUse ? array_intersect_key($use, array_flip($type->settingsPerUse())) : [];

        return $own + $field->config();
    }

    /**
     * Why the use's settings can't be taken, or null.
     *
     * @param  array<string, mixed>  $use
     */
    public static function problem(Field $field, array $use): ?string
    {
        $given = array_intersect_key($use, array_flip(self::KEYS));

        if ($given === []) {
            return null;
        }

        $type = $field->fieldType();
        $allowed = $type instanceof SettingsPerUse ? $type->settingsPerUse() : [];
        $refused = array_diff(array_keys($given), $allowed);

        if ($refused !== []) {
            return "Field [{$field->name}] can't set [".implode(', ', $refused).'] where it is used: set it on the field.';
        }

        // The type's own rules for these settings, and for their items ("templates.*").
        $names = [...array_keys($given), ...array_map(fn (string $key): string => $key.'.*', array_keys($given))];
        $validator = Validator::make($given, array_intersect_key($type->settingsRules(), array_flip($names)));

        return $validator->fails() ? "Field [{$field->name}]: ".$validator->errors()->first() : null;
    }
}
