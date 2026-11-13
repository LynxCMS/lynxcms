<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Console\Concerns\ListsSchemaByOwner;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldUsage;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Schema\SchemaOwners;
use Override;

/**
 * Lists every field with its type and where it is used: templates, and repeater or matrix items.
 * The site's fields come apart from those Lynx and its modules own (ADR-0115).
 */
final class FieldsCommand extends Command
{
    use ListsSchemaByOwner;

    private const array HEADERS = ['Field', 'Type', 'Label', 'Translatable', 'Templates', 'In items of'];

    #[Override]
    protected $signature = 'lynx:fields {--unused : Only fields nothing uses} {--json : Print JSON}';

    #[Override]
    protected $description = 'List fields (site and system apart) with their types and the templates and items that use them';

    public function handle(FieldRegistry $fields, FieldUsage $usage, SchemaOwners $owners): int
    {
        $rows = $this->rows($fields, $usage, $owners);

        if ($this->option('unused')) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['templates'] === [] && $row['items'] === []));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->tablesByOwner(self::HEADERS, array_map(fn (array $row): array => [
            'cells' => [$row['name'], $row['type'], $row['label'], $row['translatable'] ? 'yes' : 'no', implode(', ', $row['templates']), implode(', ', $row['items'])],
            'owner' => $row['owner'],
        ], $rows));

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, type: string, label: string, translatable: bool, templates: list<string>, items: list<string>, owner: string|null}>
     */
    private function rows(FieldRegistry $fields, FieldUsage $usage, SchemaOwners $owners): array
    {
        $templates = $usage->templates();
        $items = $usage->items();

        return array_values($fields->all()->sortBy('name')->map(fn (Field $field): array => [
            'name' => (string) $field->name,
            'type' => (string) $field->type,
            'label' => $field->labels()->in(),
            'translatable' => $field->isTranslatable(),
            'templates' => $templates[$field->name] ?? [],
            'items' => $items[$field->name] ?? [],
            'owner' => $owners->ownerOfField((string) $field->name),
        ])->all());
    }
}
