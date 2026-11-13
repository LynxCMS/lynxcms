<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use LynxCms\Core\Templates\Views\ViewWriter;

/**
 * The starting views a draft writes (ADR-0116): one for each new template with a URL, kept when
 * it exists unless replacing is asked, and one for each block type of those templates' matrix
 * fields. Block views belong to the field, which other templates may use too, so a draft only
 * writes the missing ones and never replaces one.
 *
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @internal
 */
final readonly class DraftViews
{
    public function __construct(
        private ViewWriter $views,
        private TemplateRegistry $templates,
        private FieldRegistry $fields,
        private FieldTypeRegistry $types,
    ) {}

    /**
     * Before building: the views the draft would write or keep.
     *
     * @return list<array{action: string, kind: string, name: string}>
     */
    public function steps(CompiledDraft $draft, DraftPlan $schema, DraftOptions $options): array
    {
        if (! $options->views) {
            return [];
        }

        $templates = $this->newTemplatesWithUrl($draft, $schema);
        $steps = array_map(fn (array $template): array => [
            'action' => $this->views->exists($template['name']) && ! $options->forceViews ? DraftPlan::KEEP : DraftPlan::WRITE,
            'kind' => 'view',
            'name' => $this->views->relativePath($template['name']),
        ], $templates);

        return [...$steps, ...$this->blockSteps($draft, $templates)];
    }

    /** After building: the views of the new templates and of their blocks. */
    public function write(DraftPlan $plan, DraftOptions $options): void
    {
        if (! $options->views) {
            return;
        }

        foreach ($plan->createdTemplates() as $name) {
            $template = $this->templates->find($name);

            if ($template instanceof Template && $template->hasUrl()) {
                $this->writeTemplate($template, $options);
            }
        }
    }

    private function writeTemplate(Template $template, DraftOptions $options): void
    {
        if ($options->forceViews || ! $this->views->exists((string) $template->name)) {
            $this->views->write($template);
        }

        foreach ($this->views->blocks($template) as ['field' => $field, 'type' => $type]) {
            if (! $this->views->blockExists((string) $field->name, $type)) {
                $this->views->writeBlock($field, $type);
            }
        }
    }

    /**
     * @return list<TemplateItem>
     */
    private function newTemplatesWithUrl(CompiledDraft $draft, DraftPlan $schema): array
    {
        $created = $schema->createdTemplates();

        return array_values(array_filter(
            $draft->templates,
            fn (array $template): bool => in_array($template['name'], $created, true) && Value::bool($template['settings']['has_url'] ?? true, true),
        ));
    }

    /**
     * One step per block view, however many of the templates share the matrix.
     *
     * @param  list<TemplateItem>  $templates
     * @return list<array{action: string, kind: string, name: string}>
     */
    private function blockSteps(CompiledDraft $draft, array $templates): array
    {
        $steps = [];

        foreach ($templates as $template) {
            foreach (DraftTemplates::fieldNames($template['fields']) as $field) {
                foreach ($this->blockTypes($draft, $field) as $type) {
                    $path = $this->views->blockRelativePath($field, $type);
                    $steps[$path] = ['action' => $this->views->blockExists($field, $type) ? DraftPlan::KEEP : DraftPlan::WRITE, 'kind' => 'view', 'name' => $path];
                }
            }
        }

        return array_values($steps);
    }

    /**
     * The block types of a matrix field, as the draft defines it or the site has it.
     *
     * @return list<string>
     */
    private function blockTypes(CompiledDraft $draft, string $name): array
    {
        $item = array_column($draft->fields, null, 'name')[$name] ?? null;
        $existing = $this->fields->find($name);
        [$typeName, $settings] = $item !== null ? [$item['type'], $item['settings']] : [$existing?->type, $existing?->config() ?? []];

        if (! is_string($typeName) || ! $this->types->has($typeName)) {
            return [];
        }

        $type = $this->types->get($typeName);

        return $type instanceof MatrixType ? $type->slugs($settings) : [];
    }
}
