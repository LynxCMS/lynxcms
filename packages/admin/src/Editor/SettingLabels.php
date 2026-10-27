<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Str;

/**
 * Field type settings and formatters in words for the field form. The settings schemas describe
 * them for agents ("Default new_open."); settings this class doesn't know keep the schema's
 * description under a label made from their name.
 *
 * @internal
 */
final class SettingLabels
{
    /** @var array<string, array{label: string, hint?: string, unit?: string, options?: array<string, string>}> */
    private const array SETTINGS = [
        'max_length' => ['label' => 'Longest text', 'hint' => 'Empty for no limit.', 'unit' => 'characters'],
        'placeholder' => ['label' => 'Placeholder', 'hint' => 'Shown in the empty input.'],
        'format' => ['label' => 'Editors write in', 'options' => ['plain' => 'Plain text', 'html' => 'Rich text', 'markdown' => 'Markdown']],
        'multiple' => ['label' => 'Editors can choose several'],
        'input' => ['label' => 'Editors choose with', 'options' => ['search' => 'A search', 'select' => 'A menu', 'radios' => 'Radio buttons', 'checkboxes' => 'Checkboxes']],
        'allowed' => ['label' => 'Components editors may choose', 'hint' => 'Comma separated. Empty allows any.'],
        'time' => ['label' => 'Also a time of day'],
        'seconds' => ['label' => 'With seconds'],
        'relative' => ['label' => 'Also accept a path on this site, such as /contact/'],
        'places' => ['label' => 'Digits after the decimal point', 'hint' => '0 to 8. Empty for 2.'],
        'min' => ['label' => 'Smallest value'],
        'max' => ['label' => 'Largest value'],
        'max_files' => ['label' => 'Most files', 'hint' => 'Empty for no limit. With 1, templates read a single file.'],
        'extensions' => ['label' => 'Allowed file types', 'hint' => 'Space separated, such as pdf docx. Empty allows the usual ones.'],
        'max_kb' => ['label' => 'Largest upload', 'unit' => 'KB', 'hint' => 'Empty for 10,240.'],
        'tags' => ['label' => 'Tags editors may use', 'hint' => 'Comma separated. Empty allows any tag.'],
        'private' => ['label' => 'Keep the files private', 'hint' => 'Stored off the public disk and served only to visitors who may open the page. Switching moves existing files.'],
        'min_width' => ['label' => 'Smallest width', 'unit' => 'px'],
        'min_height' => ['label' => 'Smallest height', 'unit' => 'px'],
        'max_width' => ['label' => 'Scale wider images down to', 'unit' => 'px', 'hint' => 'Empty for 2,560.'],
        'max_height' => ['label' => 'Scale taller images down to', 'unit' => 'px', 'hint' => 'Empty for 2,560.'],
        'templates' => ['label' => 'Templates', 'hint' => 'Comma separated template names.'],
        'parent' => ['label' => 'New items go under', 'hint' => 'A path. Empty puts them under the page itself.'],
        'columns' => ['label' => 'Columns in the table', 'hint' => 'Comma separated field names. Empty shows the title.'],
        'remove' => ['label' => 'Removing an item from the table', 'options' => ['keep' => 'Keeps its page', 'trash' => 'Moves its page to the trash']],
    ];

    /** @var array<string, string> */
    private const array FORMATTERS = [
        'smartypants' => 'Smart quotes and dashes',
        'paragraphs' => 'Paragraphs from blank lines',
        'markdown' => 'Markdown to HTML',
        'external_links' => 'Links to other sites open in a new tab',
        'heading_anchors' => 'Anchors on headings',
    ];

    /**
     * @param  array{type: string, description: string, enum: list<string>}  $definition  from SchemaForm
     * @return array{label: string, hint: string, unit: string, options: array<string, string>}
     */
    public function for(string $setting, array $definition): array
    {
        $known = self::SETTINGS[$setting] ?? null;
        $options = [];

        foreach ($definition['enum'] as $value) {
            $options[$value] = $known['options'][$value] ?? Str::headline($value);
        }

        return [
            'label' => $known['label'] ?? Str::headline($setting),
            'hint' => $known === null ? $definition['description'] : ($known['hint'] ?? ''),
            'unit' => $known['unit'] ?? '',
            'options' => $options,
        ];
    }

    public function formatter(string $name): string
    {
        return self::FORMATTERS[$name] ?? Str::headline($name);
    }
}
