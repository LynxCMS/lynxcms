<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;

/**
 * A submission's answers as label and text, from the fields stored with it, so the mail, the
 * admin and exports read the same and survive later changes to the form.
 *
 * @internal
 */
final class SubmissionPresenter
{
    /** "Ana Lima · ana@example.com": the first short answers, to tell submissions apart. */
    public function summary(Submission $submission): string
    {
        $parts = [];

        foreach (Value::list($submission->fields) as $field) {
            $field = Value::map($field);
            $value = $submission->data[Value::string($field['name'] ?? '')] ?? null;

            if (in_array($field['type'] ?? '', ['text', 'email', 'tel'], true) && is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' · ', array_slice($parts, 0, 2));
    }

    /**
     * @return list<array{name: string, label: string, value: string}>
     */
    public function lines(Submission $submission, ?string $language = null): array
    {
        $files = [];

        foreach ($submission->files->groupBy('field') as $field => $group) {
            $files[(string) $field] = Value::strings($group->pluck('name')->all());
        }

        return $this->linesFor($submission->fields, $submission->data, $files, $language);
    }

    /**
     * Answers not stored yet, such as the summary before sending.
     *
     * @param  list<array<string, mixed>>  $fields  a form snapshot
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $files  field => file names
     * @return list<array{name: string, label: string, value: string}>
     */
    public function linesFor(array $fields, array $data, array $files = [], ?string $language = null): array
    {
        $lines = [];

        foreach ($fields as $field) {
            $name = Value::string($field['name'] ?? '');
            $value = ($field['type'] ?? '') === 'file'
                ? implode(', ', $files[$name] ?? [])
                : $this->text($field, $data[$name] ?? null, $language);

            if ($value !== null && $value !== '') {
                $lines[] = ['name' => $name, 'label' => LocalizedText::from($field['label'] ?? $name)->in($language), 'value' => $value];
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $field  a stored field
     */
    private function text(array $field, mixed $value, ?string $language): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($field['type'] ?? '') {
            'checkbox' => Value::string($value ? __('lynx-forms::forms.yes') : __('lynx-forms::forms.no')),
            'select', 'radio', 'checkboxes' => implode(', ', array_map(fn (mixed $option): string => $this->option($field, $option, $language), (array) $value)),
            'group' => implode('; ', array_map(fn (mixed $row): string => $this->row($field, Value::map($row), $language), (array) $value)),
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function option(array $field, mixed $option, ?string $language): string
    {
        $label = Value::map($field['options'] ?? [])[Value::string($option)] ?? null;

        return $label === null ? Value::string($option) : LocalizedText::from($label)->in($language);
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>  $row
     */
    private function row(array $group, array $row, ?string $language): string
    {
        $parts = [];

        foreach (Value::list($group['fields'] ?? []) as $field) {
            $field = Value::map($field);
            $text = $this->text($field, $row[Value::string($field['name'] ?? '')] ?? null, $language);

            if ($text !== null) {
                $parts[] = $text;
            }
        }

        return implode(' · ', $parts);
    }
}
