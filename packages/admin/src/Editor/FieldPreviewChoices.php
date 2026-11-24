<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Support\Value;

/**
 * What the field form's Preview tab draws for a choice or page field: the control its "input"
 * setting names, as the page editor picks it (inputs/choice, PageChoices), and the first options,
 * from the form for a choice and from the allowed pages for a page field.
 *
 * @internal
 */
final readonly class FieldPreviewChoices
{
    /** Options a preview lists; the page editor shows them all. */
    public const int SHOWN = 6;

    public function __construct(private PageChoices $pages) {}

    /**
     * @param  array<array-key, mixed>  $settings  the form's settings
     * @return array{style: string, options: list<array{value: string, label: string}>}|null null for other types
     */
    public function for(string $type, array $settings): ?array
    {
        // The form holds a setting left unset as "", which the saved field leaves out.
        $settings = array_filter($settings, fn (mixed $value): bool => $value !== '' && $value !== null);

        return match ($type) {
            'choice' => ['style' => self::choiceStyle($settings), 'options' => $this->choiceOptions($settings)],
            'page' => $this->pageChoices($settings),
            default => null,
        };
    }

    /**
     * select, radios or checkboxes, as inputs/choice picks it: checkboxes pick several and radios
     * one, whatever the setting says.
     *
     * @param  array<array-key, mixed>  $settings
     */
    public static function choiceStyle(array $settings): string
    {
        $isMultiple = ($settings['multiple'] ?? false) === true;
        $style = Value::string($settings['input'] ?? ($isMultiple ? 'checkboxes' : 'select'));

        return match (true) {
            $style === 'select' => 'select',
            $isMultiple => 'checkboxes',
            default => 'radios',
        };
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return list<array{value: string, label: string}>
     */
    private function choiceOptions(array $settings): array
    {
        $rows = array_slice(Value::list($settings['options'] ?? []), 0, self::SHOWN);

        return array_map(function (mixed $row): array {
            $row = Value::map($row);
            $label = $row['label'] ?? '';

            return [
                'value' => Value::string($row['value'] ?? ''),
                'label' => Value::string(is_array($label) ? (collect($label)->filter()->first() ?? '') : $label),
            ];
        }, $rows);
    }

    /**
     * @param  array<array-key, mixed>  $settings
     * @return array{style: string, options: list<array{value: string, label: string}>}
     */
    private function pageChoices(array $settings): array
    {
        $style = PageChoices::style($settings);

        if ($style === 'search') {
            return ['style' => $style, 'options' => []];
        }

        $options = array_slice($this->pages->options($settings, []), 0, self::SHOWN);

        return ['style' => $style, 'options' => array_map(fn (array $page): array => ['value' => (string) $page['id'], 'label' => $page['title']], $options)];
    }
}
