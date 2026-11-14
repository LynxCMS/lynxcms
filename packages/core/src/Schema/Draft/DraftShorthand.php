<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Fields\FieldType;
use LynxCms\Core\Fields\ChoiceOptions;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * Reads one field of a draft (ADR-0116). The short form is "type[:argument] token token": the
 * argument fills the type's main setting ("decimal:2" is two places), a bare token is
 * "translatable" or a true setting ("multiple"), label:"Text" is the label, translatable:false keeps one value
 * for every language, and "key:value" sets a setting,
 * with list settings split on commas. The long form is the field as an array: type, label, translatable,
 * and its settings beside them.
 *
 * A field is translatable when its type can be, as the admin makes it on a site with one language,
 * because that can't change later: translatable:false opts out. A field the site has keeps its own choice
 * unless the draft states one. Repeater and matrix fields list their item fields, "name*" for a
 * required one.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 *
 * @api
 */
final readonly class DraftShorthand
{
    /** The setting a type's argument fills. */
    public const array ARGUMENTS = ['text' => 'max_length', 'textarea' => 'format', 'decimal' => 'places', 'choice' => 'options', 'page' => 'templates', 'page_table' => 'templates'];

    /** Settings every field accepts beside its type's (CreateField), with how a token value is read. */
    private const array COMMON_SETTINGS = ['fallback' => 'string', 'autoload' => 'boolean', 'description' => 'string', 'notes' => 'string'];

    private const string TRANSLATABLE = 'translatable';

    /** A label in the short form: label:"Button text". */
    public const string LABEL_TOKEN = 'label:';

    private const string REQUIRED_MARK = '*';

    public function __construct(private FieldTypeRegistry $types) {}

    /**
     * @param  string|array<string, string>|null  $label  the label when the draft gives none: the site's, for a field it has
     * @param  bool|null  $translatable  whether the field is translatable when the draft doesn't say: the site's, for a field it has
     * @return array{name: string, type: string, label: string|array<string, string>, translatable: bool, settings: array<string, mixed>}
     *
     * @throws DraftError
     */
    public function field(string $name, mixed $definition, string|array|null $label = null, ?bool $translatable = null): array
    {
        $label ??= self::label($name);

        return is_array($definition)
            ? $this->longForm($name, Value::map($definition), $label, $translatable)
            : $this->shortForm($name, Value::string($definition), $label, $translatable);
    }

    /** Whether a new field of the type is translatable when the draft doesn't say: when the type can be. */
    public function translatesByDefault(string $type): bool
    {
        return $this->types->has($type) && $this->fieldType($type)->supportsTranslation();
    }

    /**
     * An item field of a repeater or matrix: "name", "name*" (required), or its settings.
     *
     * @return array<string, mixed>
     */
    public static function itemField(mixed $entry): array
    {
        if (! is_string($entry)) {
            return Value::map($entry);
        }

        return str_ends_with($entry, self::REQUIRED_MARK)
            ? ['name' => substr($entry, 0, -1), 'required' => true]
            : ['name' => $entry];
    }

    /** The label a field gets when the draft gives none: "max_seats" is "Max seats". */
    public static function label(string $name): string
    {
        return Str::ucfirst(str_replace(['_', '-'], ' ', $name));
    }

    /**
     * How a token value of each setting of a type is read: list, boolean, integer, number or string.
     *
     * @return array<string, string>
     */
    public function settingKinds(string $type): array
    {
        $kinds = [];

        foreach ($this->fieldType($type)->settingsRules() as $key => $rules) {
            if (! str_contains($key, '.')) {
                $kinds[$key] = $this->kind(Value::array($rules));
            }
        }

        return $kinds + self::COMMON_SETTINGS;
    }

    /**
     * @param  string|array<string, string>  $label
     * @return array{name: string, type: string, label: string|array<string, string>, translatable: bool, settings: array<string, mixed>}
     */
    private function shortForm(string $name, string $definition, string|array $label, ?bool $translatable): array
    {
        preg_match_all('/(?:[^\s"]+|"[^"]*")+/', trim($definition), $matches);
        $tokens = $matches[0];
        [$type, $argument] = array_pad(explode(':', (string) array_shift($tokens), 2), 2, null);
        $type = $this->knownType($name, (string) $type);
        $field = ['name' => $name, 'type' => $type, 'label' => $label, 'translatable' => $translatable ?? $this->translatesByDefault($type), 'settings' => []];

        if ($argument !== null) {
            $field['settings'] = $this->argument($name, $type, $argument);
        }

        foreach ($tokens as $token) {
            match (true) {
                $token === self::TRANSLATABLE => $field['translatable'] = true,
                str_starts_with($token, self::TRANSLATABLE.':') => $field['translatable'] = self::isTrue(substr($token, strlen(self::TRANSLATABLE) + 1)),
                str_starts_with($token, self::LABEL_TOKEN) => $field['label'] = trim(substr($token, strlen(self::LABEL_TOKEN)), '"'),
                default => $field['settings'] = [...$field['settings'], ...$this->token($name, $type, $token)],
            };
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  string|array<string, string>  $label
     * @return array{name: string, type: string, label: string|array<string, string>, translatable: bool, settings: array<string, mixed>}
     */
    private function longForm(string $name, array $definition, string|array $label, ?bool $translatable): array
    {
        $type = $this->knownType($name, Value::string($definition['type'] ?? null));
        $settings = [...Value::map($definition['settings'] ?? []), ...array_diff_key($definition, array_flip(['type', 'label', 'translatable', 'settings']))];
        $this->assertKnownSettings($name, $type, array_keys($settings));

        return [
            'name' => $name,
            'type' => $type,
            'label' => isset($definition['label']) ? LocalizedText::from($definition['label'])->toStorage() : $label,
            'translatable' => isset($definition['translatable']) ? Value::bool($definition['translatable']) : $translatable ?? $this->translatesByDefault($type),
            'settings' => $this->itemFields($this->options($name, $settings)),
        ];
    }

    /** @return array<string, mixed> */
    private function argument(string $name, string $type, string $argument): array
    {
        $setting = self::ARGUMENTS[$type] ?? throw new DraftError("Field [{$name}]: the type [{$type}] takes no argument. Write its settings as key:value.");

        return $this->options($name, [$setting => $this->cast($argument, $this->settingKinds($type)[$setting] ?? 'string')]);
    }

    /** @return array<string, mixed> */
    private function token(string $name, string $type, string $token): array
    {
        [$key, $value] = array_pad(explode(':', $token, 2), 2, null);
        $key = (string) $key;
        $kinds = $this->settingKinds($type);
        $this->assertKnownSettings($name, $type, [$key]);
        $kind = $kinds[$key] ?? 'string';

        if ($value === null && $kind !== 'boolean') {
            throw new DraftError("Field [{$name}]: the setting [{$key}] needs a value, as {$key}:value.");
        }

        return $this->options($name, [$key => $value === null ? true : $this->cast($value, $kind)]);
    }

    /**
     * @param  list<string>  $keys
     */
    private function assertKnownSettings(string $name, string $type, array $keys): void
    {
        $known = array_keys($this->settingKinds($type));

        foreach ($keys as $key) {
            if (! in_array($key, $known, true)) {
                throw new DraftError("Field [{$name}]: the type [{$type}] has no setting [{$key}].".DraftError::suggestion($key, $known).' Its settings: '.implode(', ', $known).'.');
            }
        }
    }

    private function cast(string $value, string $kind): mixed
    {
        if ($kind === 'list') {
            return array_map(fn (?string $item): string => trim((string) $item), str_getcsv($value, ',', '"', ''));
        }

        $value = trim($value, '"');

        return match ($kind) {
            'boolean' => self::isTrue($value),
            'integer' => (int) $value,
            'number' => is_numeric($value) ? $value + 0 : $value,
            default => $value,
        };
    }

    private static function isTrue(string $value): bool
    {
        return ! in_array(strtolower(trim($value, '"')), ['false', '0', 'no'], true);
    }

    /**
     * Choice options written as labels become {value, label}, checked as the field type checks them.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function options(string $name, array $settings): array
    {
        if (! isset($settings['options']) || ! is_array($settings['options'])) {
            return $settings;
        }

        $rows = array_map(fn (mixed $option): mixed => is_string($option) ? ['value' => Str::slug($option), 'label' => $option] : $option, $settings['options']);

        try {
            return ['options' => ChoiceOptions::normalize($rows)] + $settings;
        } catch (ValidationException $e) {
            throw new DraftError("Field [{$name}]: ".implode(' ', Value::strings(Arr::flatten($e->errors()))), $e->getCode(), previous: $e);
        }
    }

    /**
     * Item fields of a repeater ("fields") and of each matrix type ("types.*.fields").
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function itemFields(array $settings): array
    {
        if (isset($settings['fields'])) {
            $settings['fields'] = array_map(self::itemField(...), Value::list($settings['fields']));
        }

        if (isset($settings['types'])) {
            $settings['types'] = array_map(fn (mixed $type): array => ['fields' => array_map(self::itemField(...), Value::list(Value::map($type)['fields'] ?? []))] + Value::map($type), Value::list($settings['types']));
        }

        return $settings;
    }

    private function knownType(string $name, string $type): string
    {
        if (! $this->types->has($type)) {
            throw new DraftError("Field [{$name}]: there is no field type [{$type}].".DraftError::suggestion($type, array_keys($this->types->all())));
        }

        return $type;
    }

    private function fieldType(string $type): FieldType
    {
        return $this->types->get($type);
    }

    /** @param  array<array-key, mixed>  $rules */
    private function kind(array $rules): string
    {
        foreach (['array' => 'list', 'boolean' => 'boolean', 'integer' => 'integer', 'numeric' => 'number'] as $rule => $kind) {
            if (in_array($rule, $rules, true)) {
                return $kind;
            }
        }

        return 'string';
    }
}
