<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldSchemaManager;
use LynxCms\Core\Fields\FieldSettings;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;

/**
 * Creates a field and its table at runtime (ADR-0004). No migration.
 */
final class CreateField extends Action
{
    /** Names that clash with built-in page properties. */
    public const array RESERVED = [
        'id', 'name', 'names', 'template', 'parent', 'parents', 'children', 'status', 'sort', 'path', 'url',
        'created', 'modified', 'published', 'created_at', 'updated_at', 'published_at', 'created_by', 'modified_by',
        'language', 'languages', 'page', 'pages', 'settings', 'has_parent', 'limit', 'start', 'include', 'join', 'lang', 'search',
    ];

    public function __construct(
        private readonly FieldTypeRegistry $types,
        private readonly FieldRegistry $fields,
        private readonly FieldSchemaManager $schema,
    ) {}

    public static function name(): string
    {
        return 'fields.create';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Create a field (and its database table) at runtime. Call describe_field_types first to see the available types and their settings. Translatable fields store one value per language.';
    }

    public static function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid', Rule::unique(Tables::FIELDS, 'uuid')],
            'name' => ['bail', 'required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(Tables::FIELDS, 'name'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array($value, self::RESERVED, true)) {
                        $fail("The name \"{$value}\" is reserved, because pages and selectors already use it. Choose another name.");
                    }
                },
                function (string $attribute, mixed $value, Closure $fail): void {
                    $max = Tables::maxFieldNameLength(DB::getTablePrefix());

                    if (is_string($value) && strlen($value) > $max) {
                        $fail("The field name may not be longer than {$max} characters.");
                    }
                },
                // A leftover or app table with the same name would make the CREATE TABLE fail.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Schema::hasTable(Tables::FIELD_PREFIX.$value)) {
                        $fail('A database table named '.Tables::FIELD_PREFIX.$value.' already exists.');
                    }
                },
            ],
            'type' => ['required', 'string'],
            'label' => ['nullable', ...LocalizedText::rules()],
            'translatable' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.fallback' => ['sometimes', Rule::in(['inherit', 'none'])],
            'settings.autoload' => ['sometimes', 'boolean'],
            'settings.description' => ['sometimes', 'nullable', ...LocalizedText::rules(2000)],
            'settings.notes' => ['sometimes', 'nullable', ...LocalizedText::rules(500)],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Machine name: lowercase letters, digits and underscores, starting with a letter, at most 51 characters, e.g. "course_price".')->required(),
            'type' => $schema->string()->description('Field type name, e.g. "text", "textarea", "integer", "toggle", "page", "component".')->required(),
            'label' => $schema->string()->description('Label shown to editors: text, or an object of language code => text (e.g. {"pt": "Função", "en": "Role"}). Defaults to the name in title case.'),
            'translatable' => $schema->boolean()->description('Store one value per language. Only for types that support translation.'),
            'settings' => $schema->object()->description('Type settings (see describe_field_types) plus "fallback": "inherit" (default) or "none", "autoload": true to load the field with every page instead of on first read, and "description" and "notes" for editors (text, or language code => text).'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $typeName = (string) $input->string('type');

        if (! $this->types->has($typeName)) {
            throw ValidationException::withMessages(['type' => "Unknown field type [{$typeName}]. Available: ".implode(', ', array_keys($this->types->all())).'.']);
        }

        $type = $this->types->get($typeName);
        $translatable = $input->bool('translatable');

        if ($translatable && ! $type->supportsTranslation()) {
            throw ValidationException::withMessages(['translatable' => "Fields of type [{$typeName}] cannot be translatable."]);
        }

        $settings = $input->array('settings');
        $typeSettings = FieldSettings::validate($type, $settings);

        $field = Field::query()->create([
            'uuid' => $input->string('uuid') ?? (string) Str::uuid(),
            'name' => $input->string('name'),
            'type' => $typeName,
            'label' => $input->get('label') ?? Str::headline((string) $input->string('name')),
            'scope' => $translatable ? Field::TRANSLATABLE : Field::SHARED,
            'settings' => array_merge($settings, $typeSettings) ?: null,
        ]);

        $this->schema->create($field);
        $this->fields->flush();

        $context->changes->schema('field', "Create {$typeName} field {$field->name}".($translatable ? ' (translatable)' : ''), $field->toSummary());

        return $field;
    }
}
