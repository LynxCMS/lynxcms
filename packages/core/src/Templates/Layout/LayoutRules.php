<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Checks a template's layout before it is saved: tabs have their own keys, and every tab, column
 * and section a field or section names exists.
 *
 * @internal
 */
final class LayoutRules
{
    /** The editor's own tabs; a template's tabs cannot take their keys. */
    public const array RESERVED_TABS = [LayoutTab::CONTENT, 'texts', 'settings'];

    /**
     * @param  array<string, array<string, mixed>>  $fields  field name => pivot settings
     * @param  array<string, mixed>  $settings  the template settings, with "tabs" and "sections"
     */
    public static function assert(array $fields, array $settings): void
    {
        $tabs = [LayoutTab::CONTENT, ...self::tabKeys($settings['tabs'] ?? [])];
        $sections = [];

        foreach (Value::list($settings['sections'] ?? []) as $section) {
            $section = Value::map($section);
            $key = Value::string($section['key'] ?? '');
            self::assertLabel("Section [{$key}]", $section['label'] ?? $key);
            self::assertPlace("Section [{$key}]", $section, $tabs);
            $sections[] = $key;
        }

        foreach ($fields as $name => $pivot) {
            self::assertPlace("Field [{$name}]", $pivot, $tabs);
            $section = Value::string($pivot['section'] ?? '');

            if ($section !== '' && ! in_array($section, $sections, true)) {
                throw ValidationException::withMessages(['fields' => "Field [{$name}] names section [{$section}], which this template does not have."]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function tabKeys(mixed $tabs): array
    {
        $keys = [];

        foreach (Value::list($tabs) as $tab) {
            $tab = Value::map($tab);
            $key = Value::string($tab['key'] ?? '');

            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $key) !== 1 || in_array($key, [...self::RESERVED_TABS, ...$keys], true)) {
                throw ValidationException::withMessages(['tabs' => "The tab key [{$key}] must be unique, lowercase letters, digits, - and _, and not one of: ".implode(', ', self::RESERVED_TABS).'.']);
            }

            self::assertLabel("Tab [{$key}]", $tab['label'] ?? '');
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $place
     * @param  list<string>  $tabs
     */
    private static function assertPlace(string $what, array $place, array $tabs): void
    {
        $tab = Value::string($place['tab'] ?? LayoutTab::CONTENT);
        $column = Value::string($place['column'] ?? TemplateLayout::MAIN);

        if (! in_array($tab, $tabs, true)) {
            throw ValidationException::withMessages(['fields' => "{$what} names tab [{$tab}], which this template does not have."]);
        }

        if (! in_array($column, TemplateLayout::COLUMNS, true)) {
            throw ValidationException::withMessages(['fields' => "{$what}: the column is main or sidebar, not [{$column}]."]);
        }
    }

    private static function assertLabel(string $what, mixed $label): void
    {
        $validator = Validator::make(['label' => $label], ['label' => ['required', ...LocalizedText::rules(255)]]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['fields' => "{$what}: ".$validator->errors()->first()]);
        }
    }
}
