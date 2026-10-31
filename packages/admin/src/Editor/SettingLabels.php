<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;
use LynxCms\Core\Support\Value;

/**
 * Field type settings and formatters in words for the field form. The settings schemas describe
 * them for agents ("Default new_open."); settings this class doesn't know keep the schema's
 * description under a label made from their name.
 *
 * @internal
 */
final class SettingLabels
{
    private const string WORDS = 'lynx-admin::field_settings.';

    /** Keys in the field_settings language file. @var array<string, array{label: string, hint?: string, unit?: string, options?: array<string, string>}> */
    private const array SETTINGS = [
        'max_length' => ['label' => 'max_length', 'hint' => 'empty_no_limit', 'unit' => 'units.characters'],
        'placeholder' => ['label' => 'placeholder', 'hint' => 'placeholder_hint'],
        'format' => ['label' => 'format', 'options' => ['plain' => 'format_plain', 'html' => 'format_html', 'markdown' => 'format_markdown']],
        'multiple' => ['label' => 'multiple'],
        'input' => ['label' => 'input', 'options' => ['search' => 'input_search', 'select' => 'input_select', 'radios' => 'input_radios', 'checkboxes' => 'input_checkboxes']],
        'allowed' => ['label' => 'allowed', 'hint' => 'allowed_hint'],
        'time' => ['label' => 'time'],
        'seconds' => ['label' => 'seconds'],
        'relative' => ['label' => 'relative'],
        'places' => ['label' => 'places', 'hint' => 'places_hint'],
        'min' => ['label' => 'min'],
        'max' => ['label' => 'max'],
        'max_files' => ['label' => 'max_files', 'hint' => 'max_files_hint'],
        'extensions' => ['label' => 'extensions', 'hint' => 'extensions_hint'],
        'max_kb' => ['label' => 'max_kb', 'unit' => 'units.kb', 'hint' => 'max_kb_hint'],
        'tags' => ['label' => 'tags', 'hint' => 'tags_hint'],
        'private' => ['label' => 'private', 'hint' => 'private_hint'],
        'min_width' => ['label' => 'min_width', 'unit' => 'units.px'],
        'min_height' => ['label' => 'min_height', 'unit' => 'units.px'],
        'max_width' => ['label' => 'max_width', 'unit' => 'units.px', 'hint' => 'empty_2560'],
        'max_height' => ['label' => 'max_height', 'unit' => 'units.px', 'hint' => 'empty_2560'],
        'templates' => ['label' => 'templates', 'hint' => 'templates_hint'],
        'parent' => ['label' => 'parent', 'hint' => 'parent_hint'],
        'columns' => ['label' => 'columns', 'hint' => 'columns_hint'],
        'remove' => ['label' => 'remove', 'options' => ['keep' => 'remove_keep', 'trash' => 'remove_trash']],
    ];

    /**
     * Settings whose words depend on the field type: a page field's "max" counts pages, where a
     * number field's is its largest value.
     *
     * @var array<string, array<string, array{label: string, hint?: string, unit?: string}>>
     */
    private const array TYPE_SETTINGS = [
        'page' => [
            'min' => ['label' => 'page_min', 'hint' => 'page_min_hint', 'unit' => 'units.pages'],
            'max' => ['label' => 'page_max', 'hint' => 'empty_no_limit', 'unit' => 'units.pages'],
        ],
    ];

    /** The formatters that have words in the language file. */
    private const array FORMATTERS = ['smartypants', 'paragraphs', 'markdown', 'external_links', 'heading_anchors'];

    /**
     * @param  array{type: string, description: string, enum: list<string>}  $definition  from SchemaForm
     * @param  string|null  $type  the field type, for settings worded per type
     * @return array{label: string, hint: string, unit: string, options: array<string, string>}
     */
    public function for(string $setting, array $definition, ?string $type = null): array
    {
        $known = self::TYPE_SETTINGS[$type ?? ''][$setting] ?? self::SETTINGS[$setting] ?? null;
        $options = [];

        foreach ($definition['enum'] as $value) {
            $options[$value] = isset($known['options'][$value]) ? $this->words($known['options'][$value]) : Str::headline($value);
        }

        return [
            'label' => isset($known['label']) ? $this->words($known['label']) : Str::headline($setting),
            'hint' => $known === null ? $definition['description'] : (isset($known['hint']) ? $this->words($known['hint']) : ''),
            'unit' => isset($known['unit']) ? $this->words($known['unit']) : '',
            'options' => $options,
        ];
    }

    public function formatter(string $name): string
    {
        return in_array($name, self::FORMATTERS, true) ? $this->words('formatters.'.$name) : Str::headline($name);
    }

    private function words(string $key): string
    {
        return Value::string(__(self::WORDS.$key));
    }
}
