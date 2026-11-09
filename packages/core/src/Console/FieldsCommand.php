<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldUsage;
use LynxCms\Core\Models\Field;
use Override;

/** Lists every field with its type and where it is used: templates, and repeater or matrix items. */
final class FieldsCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:fields {--unused : Only fields nothing uses} {--json : Print JSON}';

    #[Override]
    protected $description = 'List fields with their types and the templates and items that use them';

    public function handle(FieldRegistry $fields, FieldUsage $usage): int
    {
        $templates = $usage->templates();
        $items = $usage->items();
        $rows = array_values($fields->all()->sortBy('name')->map(fn (Field $field): array => [
            'name' => (string) $field->name,
            'type' => (string) $field->type,
            'label' => $field->labels()->in(),
            'translatable' => $field->isTranslatable(),
            'templates' => $templates[$field->name] ?? [],
            'items' => $items[$field->name] ?? [],
        ])->all());

        if ($this->option('unused')) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['templates'] === [] && $row['items'] === []));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(['Field', 'Type', 'Label', 'Translatable', 'Templates', 'In items of'], array_map(fn (array $row): array => [
            $row['name'], $row['type'], $row['label'], $row['translatable'] ? 'yes' : 'no', implode(', ', $row['templates']), implode(', ', $row['items']),
        ], $rows));

        return self::SUCCESS;
    }
}
