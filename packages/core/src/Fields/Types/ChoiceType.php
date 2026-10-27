<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Fields\ChecksSettings;
use LynxCms\Core\Fields\ChoiceOptions;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Fields\Choices\ChoiceList;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\HasDefaultValue;
use LynxCms\Core\Fields\NormalizesSettings;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * One or several of a fixed list of options, each a stable value and a label per language
 * (settings "options"). Shared across languages: the value is stored, the label is read in the
 * page's language. One row per chosen value, so "multiple" can be switched later. Templates get a
 * Choice (or null), or a ChoiceList with "multiple"; selectors compare values: color=red|blue.
 * A "default" is stored on new pages and items (ADR-0088).
 */
final class ChoiceType extends BaseFieldType implements ChecksSettings, DescribesPhpType, HasDefaultValue, NormalizesSettings, PresentsValue, ProvidesSearchText
{
    public const array INPUTS = ['select', 'radios', 'checkboxes'];

    public static function name(): string
    {
        return 'choice';
    }

    public static function description(): string
    {
        return 'One of a fixed list of options (or several with "multiple": true), each a value and a label. Labels are per language. Settings "options": [{"value": "red", "label": {"en": "Red"}}], "input": select, radios or checkboxes. Selectors compare values.';
    }

    public function multiple(): bool
    {
        return true;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', 64);
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'options' => $schema->array()->description('The options: [{"value": "red", "label": "Red" or {"en": "Red", "pt": "Vermelho"}}]. An empty value is made from the label.'),
            'multiple' => $schema->boolean()->description('Allow several options. Default false.'),
            'input' => $schema->string()->enum(self::INPUTS)->description('How editors choose: select (default for one), radios, or checkboxes (default for several).'),
            'default' => $schema->string()->description('The option new pages and new items start with: a value, or a list of values with "multiple". Must be one of the options.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'options' => ['sometimes', 'array'],
            'multiple' => ['sometimes', 'boolean'],
            'input' => ['sometimes', 'in:'.implode(',', self::INPUTS)],
            'default' => ['sometimes', 'nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) && ! (is_array($value) && array_is_list($value) && array_filter($value, is_string(...)) === $value)) {
                    $fail('The default is an option value, or a list of them.');
                }
            }],
        ];
    }

    public function checkSettings(array $settings): void
    {
        $defaults = Value::strings((array) ($settings['default'] ?? []));

        if (count($defaults) > 1 && ($settings['multiple'] ?? false) !== true) {
            throw ValidationException::withMessages(['settings.default' => 'A single choice takes one default.']);
        }

        $values = array_map(strval(...), array_keys(ChoiceOptions::labels($settings['options'] ?? [])));

        foreach ($defaults as $default) {
            if (! in_array($default, $values, true)) {
                throw ValidationException::withMessages(['settings.default' => "The default [{$default}] is not one of the options."]);
            }
        }
    }

    public function defaultValue(array $settings): mixed
    {
        $defaults = Value::strings((array) ($settings['default'] ?? []));

        return $defaults === [] ? null : $defaults;
    }

    public function normalizeSettings(array $settings): array
    {
        if (array_key_exists('options', $settings)) {
            $settings['options'] = ChoiceOptions::normalize($settings['options'], 'settings.options.');
        }

        return $settings;
    }

    public function rules(array $settings): array
    {
        $allowed = array_map(strval(...), array_keys(ChoiceOptions::labels($settings['options'] ?? [])));

        return ['nullable', 'array', function (string $attribute, mixed $values, Closure $fail) use ($allowed): void {
            $unknown = array_diff(Value::strings($values), $allowed);

            if ($unknown !== []) {
                $fail('['.implode(', ', $unknown).'] is not one of the options: '.implode(', ', $allowed).'.');
            }
        }];
    }

    /** A list of values, without blanks or repeats; one at most without "multiple". */
    public function normalize(mixed $value, array $settings): mixed
    {
        $values = [];

        foreach (is_array($value) ? $value : [$value] as $item) {
            $item = trim(Value::string($item));

            if ($item !== '' && ! in_array($item, $values, true)) {
                $values[] = $item;
            }
        }

        return ($settings['multiple'] ?? false) === true ? $values : array_slice($values, 0, 1);
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    public function sleep(mixed $value, array $settings): array
    {
        return array_map(fn (string $item): array => ['data' => $item], Value::strings($value));
    }

    public function wake(array $rows, array $settings): mixed
    {
        return array_map(fn (stdClass $row): string => Value::string($row->data), $rows);
    }

    /** A Choice or null; a ChoiceList with "multiple". Values no longer among the options are left out. */
    public function present(mixed $value, Field $field): mixed
    {
        $settings = $field->config();
        $labels = ChoiceOptions::labels($settings['options'] ?? []);
        $chosen = array_values(array_filter(Value::strings($value), fn (string $item): bool => array_key_exists($item, $labels)));
        $choices = array_map(fn (string $item): Choice => new Choice($item, LocalizedText::from($labels[$item])), array_values(array_intersect(array_map(strval(...), array_keys($labels)), $chosen)));

        return ($settings['multiple'] ?? false) === true ? new ChoiceList($choices) : ($choices[0] ?? null);
    }

    /** A Choice or null; a ChoiceList with "multiple" (see present()). */
    public function phpType(Field $field): string
    {
        return ($field->config()['multiple'] ?? false) === true ? '\\'.ChoiceList::class : '\\'.Choice::class.'|null';
    }

    /** The labels of the chosen values in the language. */
    public function searchText(mixed $value, array $settings, Language $language): string
    {
        $labels = ChoiceOptions::labels($settings['options'] ?? []);
        $chosen = array_filter(Value::strings($value), fn (string $item): bool => array_key_exists($item, $labels));

        return implode(' ', array_map(fn (string $item): string => LocalizedText::from($labels[$item])->in($language), $chosen));
    }
}
