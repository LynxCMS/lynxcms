<?php

declare(strict_types=1);

namespace LynxCms\Forms\Builder;

use Illuminate\Support\Str;
use LynxCms\Core\Fields\ChoiceOptions;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormStep;
use LynxCms\Forms\FormSchema;

/**
 * Reads a form page into a definition (ADR-0053): each switched-on block of form_fields becomes
 * a field, a step block starts a new step (blocks before the first one make step 1), and a group
 * takes the blocks indented under it. Texts keep every language, read with fallback.
 *
 * @internal
 */
final class PageFormCompiler
{
    private const array WIDTHS = [100, 50, 33, 25];

    public function compile(Page $page): PageForm
    {
        $languages = lynx()->languages->all();
        $byLanguage = fn (string $field): ?array => $this->texts(array_combine(
            $languages->map(fn ($language): string => (string) $language->code)->all(),
            $languages->map(fn (Language|string|null $language): mixed => $page->getUnformatted($field, $language))->all(),
        ));
        [$fields, $steps] = $this->fieldsAndSteps(self::blocks($page), $byLanguage('title') ?? $page->name());

        return new PageForm(
            (int) $page->id,
            $page->name(),
            $byLanguage('title') ?? $page->name(),
            $fields,
            $steps,
            $this->recipients(Value::string($page->getUnformatted(FormSchema::RECIPIENTS))),
            ['success' => $byLanguage(FormSchema::SUCCESS), 'error' => $byLanguage(FormSchema::ERROR), 'auto_reply' => $byLanguage(FormSchema::AUTO_REPLY)],
            $page->templateField(FormSchema::ONCE_PER_USER) !== null && Value::bool($page->getUnformatted(FormSchema::ONCE_PER_USER)),
        );
    }

    /**
     * The switched-on blocks, with their position in form_fields for error keys.
     *
     * @return list<array{index: int, type: string, depth: int, values: array<string, mixed>}>
     */
    public static function blocks(Page $page): array
    {
        $blocks = [];

        foreach (Value::list($page->getUnformatted(FormSchema::FIELDS)) as $index => $item) {
            $item = Value::map($item);

            if ((bool) ($item['enabled'] ?? true)) {
                $blocks[] = ['index' => $index, 'type' => Value::string($item['type'] ?? ''), 'depth' => Value::int($item['depth'] ?? 0), 'values' => Value::map($item['values'] ?? [])];
            }
        }

        return $blocks;
    }

    /** A block's field name: its own, or one made from its default-language label ("Your name" → your_name). */
    public static function nameOf(array $block): string
    {
        $name = trim(Value::string($block['values'][FormSchema::NAME] ?? ''));

        return $name !== '' ? $name : Str::slug(LocalizedText::from(self::label($block))->in((string) lynx()->languages->default()->code), '_');
    }

    /** @return string|array<string, string> */
    public static function label(array $block): string|array
    {
        $label = $block['values'][FormSchema::LABEL] ?? '';

        return is_array($label) ? array_map(Value::string(...), Value::map($label)) : Value::string($label);
    }

    /**
     * @param  list<array{index: int, type: string, depth: int, values: array<string, mixed>}>  $blocks
     * @param  string|array<string, string>  $firstStepTitle
     * @return array{0: list<FormField>, 1: list<FormStep>}
     */
    private function fieldsAndSteps(array $blocks, string|array $firstStepTitle): array
    {
        $fields = [];
        $steps = [];
        $current = null;
        $group = null;

        foreach ($blocks as $block) {
            if ($block['type'] === 'step') {
                $group = $this->closeGroup($group, $fields);
                $steps[] = $current ?? ($fields === [] ? null : ['title' => $firstStepTitle, 'fields' => array_map(fn (FormField $field): string => $field->name, $fields)]);
                $current = ['title' => self::label($block), 'fields' => []];

                continue;
            }

            if ($block['depth'] > 0 && $group instanceof FormField) {
                $group = $group->fields([...$group->fields, $this->field($block)]);

                continue;
            }

            $group = $this->closeGroup($group, $fields);
            $field = $this->field($block);
            $block['type'] === 'group' ? $group = $field : $fields[] = $field;

            if ($current !== null) {
                $current['fields'][] = $field->name;
            }
        }

        $this->closeGroup($group, $fields);

        if ($current !== null) {
            $steps[] = $current;
        }

        $steps = array_values(array_filter($steps));

        return [$fields, array_map(fn (array $step, int $index): FormStep => new FormStep('step-'.($index + 1), $step['title'], $step['fields']), $steps, array_keys($steps))];
    }

    /**
     * @param  list<FormField>  $fields
     */
    private function closeGroup(?FormField $group, array &$fields): null
    {
        if ($group instanceof FormField) {
            $fields[] = $group;
        }

        return null;
    }

    /**
     * @param  array{index: int, type: string, depth: int, values: array<string, mixed>}  $block
     */
    private function field(array $block): FormField
    {
        $values = $block['values'];
        $field = FormField::{$block['type']}(self::nameOf($block))->label(self::label($block))->required((bool) ($values[FormSchema::REQUIRED] ?? false));
        $help = $this->texts(Value::map($values[FormSchema::HELP] ?? []));
        $width = Value::int($values[FormSchema::WIDTH] ?? 100, 100);
        $showIf = trim(Value::string($values[FormSchema::SHOW_IF] ?? ''));

        $field = $help === null ? $field : $field->help($help);
        $field = in_array($width, self::WIDTHS, true) ? $field->width($width) : $field;
        $field = $showIf === '' ? $field : $this->condition($field, $showIf, Value::string($values[FormSchema::SHOW_IF_VALUES] ?? ''));

        return $this->typeSettings($field, $block['type'], $values);
    }

    /**
     * A block's condition: values ("press, other"), yes or no for a tick box, or a comparison
     * (">= 50", "!= 3").
     *
     * @return array{0: string, 1: list<string>} operator for showWhen(), or "in" with values
     */
    public static function parseCondition(string $values): array
    {
        if (preg_match('/^(<=|>=|!=|<|>|=)\s*(.+)$/', trim($values), $match) === 1) {
            return [$match[1], [trim($match[2])]];
        }

        $words = ['yes' => '1', 'sim' => '1', 'true' => '1', 'no' => '0', 'não' => '0', 'nao' => '0', 'false' => '0'];
        $list = array_filter(array_map(fn (string $item): string => mb_strtolower(trim($item, " .\t")), explode(',', $values)), fn (string $item): bool => $item !== '');

        return ['in', array_values(array_map(fn (string $item): string => $words[$item] ?? $item, $list))];
    }

    private function condition(FormField $field, string $target, string $values): FormField
    {
        [$operator, $list] = self::parseCondition($values);

        return $operator === 'in' ? $field->showIf($target, ...$list) : $field->showWhen($target, $operator, $list[0]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function typeSettings(FormField $field, string $type, array $values): FormField
    {
        $max = Value::int($values[FormSchema::MAX] ?? 0);

        return match ($type) {
            'select', 'radio', 'checkboxes' => $field->options(ChoiceOptions::labels($values[FormSchema::OPTIONS] ?? [])),
            'file' => $field->extensions($this->list(Value::string($values[FormSchema::EXTENSIONS] ?? '')))
                ->maxKb(max(1, Value::int($values[FormSchema::MAX_KB] ?? null, 10240)))
                ->maxFiles(max(1, Value::int($values[FormSchema::MAX_FILES] ?? null, 1))),
            'group' => $field->maxRows(max(1, Value::int($values[FormSchema::MAX_ROWS] ?? null, 10))),
            default => $max > 0 ? $field->max($max) : $field,
        };
    }

    /**
     * Texts by language code without the empty ones, or null when there is none.
     *
     * @param  array<array-key, mixed>  $texts
     * @return array<string, string>|null
     */
    private function texts(array $texts): ?array
    {
        $texts = array_filter(array_map(fn (mixed $text): string => trim(Value::string($text)), Value::map($texts)), fn (string $text): bool => $text !== '');

        return $texts === [] ? null : $texts;
    }

    /**
     * @return list<string>
     */
    private function list(string $commaSeparated): array
    {
        return array_values(array_filter(array_map(fn (string $item): string => strtolower(trim($item, " .\t")), explode(',', $commaSeparated)), fn (string $item): bool => $item !== ''));
    }

    /**
     * @return list<string>|null
     */
    private function recipients(string $emails): ?array
    {
        $list = array_values(array_filter(array_map(trim(...), explode(',', $emails)), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        return $list === [] ? null : $list;
    }
}
