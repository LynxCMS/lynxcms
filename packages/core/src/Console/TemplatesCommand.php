<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use LynxCms\Core\Console\Concerns\ListsSchemaByOwner;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaOwners;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FieldPlacement;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;
use Override;

/**
 * Lists the templates with their pages, fields and views, the site's apart from those Lynx and its
 * modules own (ADR-0115); or one template's fields in order.
 */
final class TemplatesCommand extends Command
{
    use ListsSchemaByOwner;

    private const array TEMPLATE_HEADERS = ['Template', 'Label', 'URL', 'Pages', 'Fields', 'View'];

    private const array FIELD_HEADERS = ['Field', 'Type', 'Label', 'Translatable', 'Required', 'Section', 'Tab'];

    #[Override]
    protected $signature = 'lynx:templates {template? : List this template\'s fields} {--json : Print JSON}';

    #[Override]
    protected $description = 'List templates (site and system apart), or a template\'s fields with their types, sections and tabs';

    public function handle(TemplateRegistry $templates, ViewFactory $views, FieldPlacement $placement, SchemaOwners $owners): int
    {
        $name = Value::nullableString($this->argument('template'));

        if ($name === null) {
            $rows = $this->templates($templates, $views, $owners);

            return $this->show($rows, fn () => $this->tablesByOwner(self::TEMPLATE_HEADERS, array_map(
                fn (array $row): array => ['cells' => $this->cells($row), 'owner' => $row['owner']],
                $rows,
            )));
        }

        $template = $templates->find($name);

        if (! $template instanceof Template) {
            $this->components->error("No template is named [{$name}].");

            return self::FAILURE;
        }

        $rows = $this->fields($template, $templates, $placement);

        return $this->show($rows, fn () => $this->fittedTable(self::FIELD_HEADERS, array_map($this->cells(...), $rows)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(): void  $table
     */
    private function show(array $rows, callable $table): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $table();

        return self::SUCCESS;
    }

    /**
     * A row's values as table cells; the owner has a table of its own.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function cells(array $row): array
    {
        unset($row['owner']);

        return array_values(array_map(fn (mixed $value): string => match (true) {
            is_bool($value) => $value ? 'yes' : 'no',
            $value === null => '',
            default => Value::string($value),
        }, $row));
    }

    /**
     * @return list<array{name: string, label: string, has_url: bool, pages: int, fields: int, view: bool, owner: string|null}>
     */
    private function templates(TemplateRegistry $templates, ViewFactory $views, SchemaOwners $owners): array
    {
        $directory = Value::string(config('lynx.views_path', 'templates'));

        return array_values($templates->all()->sortBy('name')->map(fn (Template $template): array => [
            'name' => (string) $template->name,
            'label' => $template->labels()->in(),
            'has_url' => $template->hasUrl(),
            'pages' => lynx()->pages->find("template={$template->name}, include=all, limit=1")->total(),
            'fields' => $templates->fields($template)->count(),
            'view' => $views->exists($directory.'.'.$template->name),
            'owner' => $owners->ownerOfTemplate((string) $template->name),
        ])->all());
    }

    /**
     * @return list<array{name: string, type: string, label: string, translatable: bool, required: bool, section: string|null, tab: string}>
     */
    private function fields(Template $template, TemplateRegistry $templates, FieldPlacement $placement): array
    {
        return array_values($templates->fields($template)->map(fn (TemplateField $templateField): array => [
            'name' => $templateField->name(),
            'type' => (string) $templateField->field->type,
            'label' => $templateField->label(),
            'translatable' => $templateField->field->isTranslatable(),
            'required' => $templateField->required(),
            'section' => $placement->sectionLabel($template, $templateField),
            'tab' => $placement->tab($template, $templateField),
        ])->all());
    }
}
