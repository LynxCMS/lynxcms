<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use LynxCms\Core\Support\Value;

/**
 * The reverse of FieldListInput: a template's stored fields and sections as one field list, each
 * section nested where its first field is ({"section": "hero", ..., "fields": [...]}) and sections
 * without fields last. Schema files and the Templates screen write it; template actions read it.
 *
 * @internal
 */
final class FieldListOutput
{
    /**
     * @param  list<array{name: string, settings: array<string, mixed>}>  $fields  in order
     * @param  list<mixed>  $sections  as stored in the template settings
     * @return list<string|array<string, mixed>>
     */
    public static function nest(array $fields, array $sections): array
    {
        $entries = [];

        foreach ($sections as $section) {
            $section = Value::map($section);
            $key = Value::string($section['key'] ?? '');
            $entries[$key] = ['section' => $key] + array_diff_key($section, ['key' => true]);
        }

        $list = [];
        $positions = [];
        $members = [];

        foreach ($fields as ['name' => $name, 'settings' => $settings]) {
            $key = Value::string($settings['section'] ?? '');
            unset($settings['section']);
            $spec = $settings === [] ? $name : ['name' => $name] + $settings;

            if (! isset($entries[$key])) {
                $list[] = $spec;

                continue;
            }

            if (! isset($positions[$key])) {
                $positions[$key] = count($list);
                $list[] = $key;
            }

            $members[$key][] = $spec;
        }

        foreach ($positions as $key => $position) {
            $list[$position] = $entries[$key] + ['fields' => $members[$key]];
        }

        foreach (array_diff_key($entries, $positions) as $section) {
            $list[] = $section + ['fields' => []];
        }

        return array_values($list);
    }
}
