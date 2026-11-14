<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use LynxCms\Core\Fields\FieldTypeRegistry;

/**
 * A JSON Schema of the draft format (lynx:draft --json-schema, ADR-0116), built from the registered
 * field types, so editors with a YAML language server check and complete a draft as it is typed.
 * The short form is checked by its type name only; the long form is checked setting by setting,
 * from each type's settingsSchema().
 *
 * @api
 */
final readonly class DraftJsonSchema
{
    /** The first line that points a draft in the same folder to its schema, for the YAML language server. */
    public const string MODELINE = '# yaml-language-server: $schema=./draft.schema.json';

    private const string DIALECT = 'https://json-schema.org/draft/2020-12/schema';

    private const string ITEM_NAME_PATTERN = '^[a-z][a-z0-9_]*\*?$';

    public function __construct(private FieldTypeRegistry $types) {}

    public function toJson(): string
    {
        return json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            '$schema' => self::DIALECT,
            'title' => 'Lynx draft',
            'description' => 'Templates and fields for php artisan lynx:draft.',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'fields' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/$defs/field']],
                'templates' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/$defs/template']],
                'pages' => $this->pages(),
            ],
            '$defs' => [
                'field' => ['anyOf' => [['$ref' => '#/$defs/shortField'], ['$ref' => '#/$defs/longField']]],
                'shortField' => $this->shortField(),
                'longField' => ['anyOf' => array_map($this->longField(...), array_keys($this->types->all()))],
                'template' => $this->template(),
                'entry' => $this->entry(),
                'itemFields' => ['type' => 'array', 'items' => ['anyOf' => [['type' => 'string', 'pattern' => self::ITEM_NAME_PATTERN], ['type' => 'object', 'required' => ['name']]]]],
                'label' => ['anyOf' => [['type' => 'string'], ['type' => 'object', 'additionalProperties' => ['type' => 'string']]]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function shortField(): array
    {
        $arguments = implode(', ', array_map(fn (string $type, string $setting): string => "{$type}:{$setting}", array_keys(DraftShorthand::ARGUMENTS), DraftShorthand::ARGUMENTS));

        return [
            'type' => 'string',
            'pattern' => '^(?:'.implode('|', array_map(preg_quote(...), array_keys($this->types->all()))).')(?::\S+)?(?:\s.*)?$',
            'description' => 'type[:argument] then flags (translatable, true settings), label:"Text" and key:value settings. Text, textarea and URL fields are translatable unless given translatable:false. Arguments: '.$arguments.'.',
        ];
    }

    /** @return array<string, mixed> */
    private function longField(string $type): array
    {
        $settings = array_map(fn (Type $schema): array => $schema->toArray(), $this->types->get($type)->settingsSchema(new JsonSchemaTypeFactory));
        $settings = array_intersect_key($settings, array_flip(['fields', 'types'])) === [] ? $settings : $this->withItemFields($settings);
        $class = $this->types->all()[$type];

        return [
            'type' => 'object',
            'description' => $class::description(),
            'required' => ['type'],
            'additionalProperties' => false,
            'properties' => [
                'type' => ['const' => $type],
                'label' => ['$ref' => '#/$defs/label'],
                'translatable' => ['type' => 'boolean', 'description' => 'Whether the field has a value per language. Defaults to true when the type can be translated.'],
                'settings' => ['type' => 'object'],
                ...$settings,
                'fallback' => ['enum' => ['inherit', 'none']],
                'autoload' => ['type' => 'boolean'],
                'description' => ['$ref' => '#/$defs/label'],
                'notes' => ['$ref' => '#/$defs/label'],
            ],
        ];
    }

    /**
     * Item fields in a draft are names too ("name*" for a required one).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withItemFields(array $settings): array
    {
        if (isset($settings['fields'])) {
            $settings['fields'] = ['$ref' => '#/$defs/itemFields'];
        }
        if (isset($settings['types'])) {
            $settings['types'] = ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['fields' => ['$ref' => '#/$defs/itemFields']]]];
        }

        return $settings;
    }

    /**
     * Pages by URL path: a title, nothing (the title from the path), or a template, a title and
     * field values.
     *
     * @return array<string, mixed>
     */
    private function pages(): array
    {
        return [
            'type' => 'object',
            'description' => 'Pages to create, unpublished, by URL path. A path the site has is kept as it is.',
            'propertyNames' => ['pattern' => '^/[a-z0-9_/-]+$'],
            'additionalProperties' => ['anyOf' => [
                ['type' => 'string', 'description' => 'The title; the template is the one named like the last segment.'],
                ['type' => 'null'],
                ['type' => 'object', 'properties' => [
                    'template' => ['type' => 'string', 'description' => 'Default: the template named like the last segment.'],
                    'title' => ['$ref' => '#/$defs/label'],
                ], 'additionalProperties' => true, 'description' => 'Other keys are field values; required fields need one.'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function template(): array
    {
        $names = ['type' => 'array', 'items' => ['type' => 'string']];
        $properties = [
            'label' => ['$ref' => '#/$defs/label'],
            'url' => ['type' => 'boolean', 'description' => 'Whether pages have a URL. Default true; false for settings and reusable content.'],
            'singleton' => ['type' => 'boolean', 'description' => 'Only one page may use the template.'],
            'children' => $names + ['description' => 'Templates children may use.'],
            'parents' => $names + ['description' => 'Templates the parent may use.'],
            'allowed' => $names + ['description' => 'Templates pages may switch to.'],
            'tabs' => ['anyOf' => [['type' => 'object', 'additionalProperties' => ['type' => 'string']], ['type' => 'array']], 'description' => 'Editor tabs as key: label.'],
            'settings' => ['type' => 'object', 'description' => 'Template settings the keys above don\'t express, as schema files write them.'],
            'fields' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/entry']],
        ];

        return ['type' => 'object', 'additionalProperties' => false, 'properties' => array_intersect_key($properties, array_flip(DraftTemplates::KEYS))];
    }

    /** @return array<string, mixed> */
    private function entry(): array
    {
        return ['anyOf' => [
            ['type' => 'string', 'pattern' => self::ITEM_NAME_PATTERN, 'description' => 'A field, "name*" for one required on this template.'],
            ['type' => 'object', 'required' => ['section'], 'properties' => [
                'section' => ['type' => 'string'], 'label' => ['$ref' => '#/$defs/label'], 'tab' => ['type' => 'string'],
                'column' => ['enum' => ['main', 'sidebar']], 'collapsed' => ['type' => 'boolean'],
                'fields' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/entry']],
            ]],
            ['type' => 'object', 'required' => ['name'], 'description' => 'A field with settings for this template: required, label, show_if, width...'],
            ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 1, 'additionalProperties' => ['$ref' => '#/$defs/field'], 'description' => 'A field defined in place: {name: type}.'],
        ]];
    }
}
