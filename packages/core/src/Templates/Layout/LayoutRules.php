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
            self::assertLabel(self::what('section', $key), $section['label'] ?? $key);
            self::assertPlace(self::what('section', $key), $section, $tabs);
            $sections[] = $key;
        }

        foreach ($fields as $name => $pivot) {
            self::assertPlace(self::what('field', $name), $pivot, $tabs);
            $section = Value::string($pivot['section'] ?? '');

            if ($section !== '' && ! in_array($section, $sections, true)) {
                throw ValidationException::withMessages(['fields' => Value::string(__('lynx::structure.layout.no_section', ['field' => $name, 'section' => $section]))]);
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

            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key) !== 1 || in_array($key, [...self::RESERVED_TABS, ...$keys], true)) {
                throw ValidationException::withMessages(['tabs' => Value::string(__('lynx::structure.layout.tab_key', ['tab' => $key, 'reserved' => implode(', ', self::RESERVED_TABS)]))]);
            }

            self::assertLabel(self::what('tab', $key), $tab['label'] ?? '');
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
            throw ValidationException::withMessages(['fields' => Value::string(__('lynx::structure.layout.no_tab', ['what' => $what, 'tab' => $tab]))]);
        }

        if (! in_array($column, TemplateLayout::COLUMNS, true)) {
            throw ValidationException::withMessages(['fields' => Value::string(__('lynx::structure.layout.column', ['what' => $what, 'column' => $column]))]);
        }
    }

    /** What an error is about: "field", "section" or "tab", with its name. */
    private static function what(string $kind, string $name): string
    {
        return Value::string(__("lynx::structure.layout.{$kind}", ['name' => $name]));
    }

    private static function assertLabel(string $what, mixed $label): void
    {
        $validator = Validator::make(['label' => $label], ['label' => ['required', ...LocalizedText::rules(255)]]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['fields' => Value::string(__('lynx::structure.layout.problem', ['what' => $what, 'problem' => $validator->errors()->first()]))]);
        }
    }
}
