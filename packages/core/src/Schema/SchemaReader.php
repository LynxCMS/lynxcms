<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the schema YAML files that SchemaExporter writes (ADR-0014).
 *
 * @phpstan-import-type SchemaFiles from SchemaSync
 * @phpstan-import-type LanguageItem from SchemaSync
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @internal
 */
final readonly class SchemaReader
{
    public function __construct(private Filesystem $files) {}

    /**
     * Parse the YAML files into typed items. Missing keys get the same defaults export writes.
     *
     * @return SchemaFiles
     */
    public function read(string $directory): array
    {
        $parse = fn (string $kind): array => array_map(
            fn (string $file): array => Value::map(Yaml::parseFile($file)),
            Value::strings($this->files->glob(rtrim($directory, '/')."/{$kind}/*.yaml")),
        );

        return [
            'languages' => array_map($this->language(...), $parse('languages')),
            'fields' => array_map($this->field(...), $parse('fields')),
            'templates' => array_map($this->template(...), $parse('templates')),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $l
     * @return LanguageItem
     */
    private function language(array $l): array
    {
        return [
            'uuid' => Value::string($l['uuid'] ?? null),
            'code' => Value::string($l['code'] ?? null),
            'name' => Value::string($l['name'] ?? null),
            'label' => Value::nullableString($l['label'] ?? null),
            'locale' => Value::nullableString($l['locale'] ?? null),
            'fallback' => Value::nullableString($l['fallback'] ?? null),
            'prefix' => Value::string($l['prefix'] ?? null),
            'active' => Value::bool($l['active'] ?? true, true),
            'default' => Value::bool($l['default'] ?? false),
            'provider_codes' => isset($l['provider_codes']) ? Value::map($l['provider_codes']) : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $f
     * @return FieldItem
     */
    private function field(array $f): array
    {
        return [
            'uuid' => Value::string($f['uuid'] ?? null),
            'name' => Value::string($f['name'] ?? null),
            'type' => Value::string($f['type'] ?? null),
            'label' => LocalizedText::from($f['label'] ?? null)->toStorage(),
            'translatable' => Value::bool($f['translatable'] ?? false),
            'settings' => Value::map($f['settings'] ?? []),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $t
     * @return TemplateItem
     */
    private function template(array $t): array
    {
        return [
            'uuid' => Value::string($t['uuid'] ?? null),
            'name' => Value::string($t['name'] ?? null),
            'label' => LocalizedText::from($t['label'] ?? null)->toStorage(),
            'settings' => Value::map($t['settings'] ?? []),
            'fields' => array_values(Value::array($t['fields'] ?? [])),
        ];
    }
}
