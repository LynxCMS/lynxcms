<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory as ViewFactory;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FieldPlacement;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;
use Override;

/** Lists the templates with their pages, fields and views, or one template's fields in order. */
final class TemplatesCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:templates {template? : List this template\'s fields} {--json : Print JSON}';

    #[Override]
    protected $description = 'List templates, or a template\'s fields with their types, sections and tabs';

    public function handle(TemplateRegistry $templates, ViewFactory $views, FieldPlacement $placement): int
    {
        $name = Value::nullableString($this->argument('template'));

        if ($name === null) {
            return $this->show(['Template', 'Label', 'URL', 'Pages', 'Fields', 'View'], $this->templates($templates, $views));
        }

        $template = $templates->find($name);

        if (! $template instanceof Template) {
            $this->components->error("No template is named [{$name}].");

            return self::FAILURE;
        }

        return $this->show(['Field', 'Type', 'Label', 'Translatable', 'Required', 'Section', 'Tab'], $this->fields($template, $templates, $placement));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function show(array $headers, array $rows): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table($headers, array_map(fn (array $row): array => array_map($this->cell(...), array_values($row)), $rows));

        return self::SUCCESS;
    }

    private function cell(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'yes' : 'no',
            $value === null => '',
            default => Value::string($value),
        };
    }

    /**
     * @return list<array{name: string, label: string, has_url: bool, pages: int, fields: int, view: bool}>
     */
    private function templates(TemplateRegistry $templates, ViewFactory $views): array
    {
        $directory = Value::string(config('lynx.views_path', 'templates'));

        return array_values($templates->all()->sortBy('name')->map(fn (Template $template): array => [
            'name' => (string) $template->name,
            'label' => $template->labels()->in(),
            'has_url' => $template->hasUrl(),
            'pages' => lynx()->pages->find("template={$template->name}, include=all, limit=1")->total(),
            'fields' => $templates->fields($template)->count(),
            'view' => $views->exists($directory.'.'.$template->name),
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
