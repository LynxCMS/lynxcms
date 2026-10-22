<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Support\Value;

/**
 * A list of choices (a value and a label per language) as the choices editor shows it: every row
 * with an input for each language. Used for a choice field's options and a form's options.
 *
 * @api
 */
final class ChoicesForm
{
    /**
     * @return list<array{value: string, label: array<string, string>}>
     */
    public static function toForm(mixed $options): array
    {
        $codes = array_column(LocalizedForm::languages(), 'code');

        return array_map(function (mixed $row) use ($codes): array {
            $row = Value::map($row);
            $label = $row['label'] ?? '';
            $labels = is_array($label) ? Value::map($label) : [];

            return [
                'value' => Value::string($row['value'] ?? ''),
                'label' => array_combine($codes, array_map(fn (string $code): string => Value::string(is_array($label) ? ($labels[$code] ?? '') : $label), $codes)),
            ];
        }, Value::list($options));
    }

    /**
     * @return list<array{value: string, label: array<string, string>}>|null rows as typed; the field type cleans them
     */
    public static function fromForm(mixed $rows): ?array
    {
        $options = array_map(fn (mixed $row): array => [
            'value' => trim(Value::string(Value::map($row)['value'] ?? '')),
            'label' => array_map(fn (mixed $text): string => Value::string($text), Value::map(Value::map($row)['label'] ?? [])),
        ], Value::list($rows));

        return $options === [] ? null : $options;
    }
}
