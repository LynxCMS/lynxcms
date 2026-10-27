<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Closure;
use Illuminate\Support\Facades\Validator;
use LynxCms\Contracts\Fields\FieldType;
use LynxCms\Core\Support\Value;

/**
 * Validates a field type's settings with errors keyed like the input ("settings.max_length"), so
 * the admin shows them by their input and agents know which setting to fix.
 *
 * @internal
 */
final class FieldSettings
{
    /**
     * @param  array<array-key, mixed>  $settings
     * @return array<string, mixed> the settings the type's rules accept
     */
    public static function validate(FieldType $type, array $settings): array
    {
        $rules = [];

        foreach ($type->settingsRules() as $key => $rule) {
            $rules["settings.{$key}"] = $rule;
        }

        // Null removes a setting on update; there is nothing to validate.
        $values = array_filter($settings, fn (mixed $value): bool => $value !== null);
        $rules['settings.formatters'] = ['sometimes', 'array', function (string $attribute, mixed $names, Closure $fail) use ($type): void {
            $problem = self::formatterProblem(Value::array($names), $type::name());

            if ($problem !== null) {
                $fail($problem);
            }
        }];

        $validated = Value::map(Validator::make(['settings' => $values], $rules)->validate()['settings'] ?? []);

        return $type instanceof NormalizesSettings ? $type->normalizeSettings($validated) : $validated;
    }

    /**
     * Checks settings that depend on each other, on everything the field will have (ADR-0088).
     *
     * @param  array<string, mixed>  $settings
     */
    public static function check(FieldType $type, array $settings): void
    {
        if ($type instanceof ChecksSettings) {
            $type->checkSettings($settings);
        }
    }

    /**
     * Why a list of formatters cannot be used on a field type, or null when it can (ADR-0031).
     *
     * @param  array<array-key, mixed>  $names
     */
    private static function formatterProblem(array $names, string $fieldType): ?string
    {
        $formatters = app(FormatterRegistry::class);

        foreach ($names as $name) {
            if (! is_string($name) || ! $formatters->has($name)) {
                return 'Unknown formatter ['.Value::string($name).']. Available: '.implode(', ', array_keys($formatters->forType($fieldType))).'.';
            }

            if (! $formatters->get($name)->appliesTo($fieldType)) {
                return "The formatter [{$name}] does not apply to {$fieldType} fields.";
            }
        }

        return null;
    }
}
