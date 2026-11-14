<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\SchemaTypes;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Builds a compiled draft (ADR-0116): its fields and templates through schema sync, in one
 * transaction and without deletes. A draft replaces the field list of a template the site has, so
 * one that leaves fields out is refused unless pruning is asked. Then, when files are written, a starting view for each new
 * template with a URL, the draft's files in the site's schema folder, and the PHPStan and IDE
 * types. Each file only where the site keeps it: no schema folder or type file is started here.
 *
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @api
 */
final readonly class DraftBuilder
{
    public function __construct(
        private SchemaSync $sync,
        private SchemaExporter $exporter,
        private FieldRegistry $fields,
        private TemplateRegistry $templates,
        private DraftViews $views,
        private DraftPageWriter $pages,
        private SchemaTypes $types,
        private DraftPaths $paths,
    ) {}

    public function build(CompiledDraft $draft, DraftOptions $options): DraftResult
    {
        $this->assertKeepsFields($draft, $options);
        $plan = $this->plan($draft, $options);
        $sync = $this->sync->applyItems($draft->fields, $draft->templates, $options->dryRun);

        if ($sync->applied && ! $options->dryRun && $options->pages) {
            $this->pages->create($draft->pages);
        }

        if (! $sync->applied || ! $options->writeFiles) {
            return new DraftResult($plan, $sync);
        }

        $this->views->write($plan, $options);
        $notes = $this->writeSchemaFiles($draft, $plan);
        $this->refreshTypes($plan);

        return new DraftResult($plan, $sync, $notes);
    }

    public function plan(CompiledDraft $draft, DraftOptions $options): DraftPlan
    {
        $steps = [
            ...array_filter(array_map($this->fieldStep(...), $draft->fields)),
            ...array_filter(array_map($this->templateStep(...), $draft->templates)),
            ...$this->removalSteps($draft),
        ];
        $schema = new DraftPlan(array_values($steps));

        $pages = $options->pages ? $this->pages->steps($draft->pages) : [];

        if (! $options->writeFiles) {
            return new DraftPlan([...$schema->steps, ...$pages]);
        }

        return new DraftPlan([...$schema->steps, ...$pages, ...$this->views->steps($draft, $schema, $options), ...$this->fileSteps($draft, $schema)]);
    }

    /**
     * @param  FieldItem  $item
     * @return array{action: string, kind: string, name: string}|null
     */
    private function fieldStep(array $item): ?array
    {
        $existing = $this->fields->find($item['name']);

        if (! $existing instanceof Field) {
            return ['action' => DraftPlan::CREATE, 'kind' => 'field', 'name' => $item['name']];
        }

        $current = $this->exporter->fieldData($existing);

        return $current['label'] !== $item['label'] || $current['settings'] != $item['settings'] || $current['translatable'] !== $item['translatable']
            ? ['action' => DraftPlan::UPDATE, 'kind' => 'field', 'name' => $item['name']]
            : null;
    }

    /**
     * @param  TemplateItem  $item
     * @return array{action: string, kind: string, name: string}|null
     */
    private function templateStep(array $item): ?array
    {
        $existing = $this->templates->find($item['name']);

        if (! $existing instanceof Template) {
            return ['action' => DraftPlan::CREATE, 'kind' => 'template', 'name' => $item['name']];
        }

        $current = $this->exporter->templateData($existing);

        return $current['label'] !== $item['label'] || $current['fields'] != $item['fields'] || $current['settings'] != $item['settings']
            ? ['action' => DraftPlan::UPDATE, 'kind' => 'template', 'name' => $item['name']]
            : null;
    }

    /**
     * A step for each field the draft leaves out of a template the site has.
     *
     * @return list<array{action: string, kind: string, name: string}>
     */
    private function removalSteps(CompiledDraft $draft): array
    {
        $steps = [];

        foreach ($draft->templates as $item) {
            foreach ($this->removedFields($item) as $field) {
                $steps[] = ['action' => DraftPlan::REMOVE, 'kind' => 'field', 'name' => "{$field} from template {$item['name']}"];
            }
        }

        return $steps;
    }

    private function assertKeepsFields(CompiledDraft $draft, DraftOptions $options): void
    {
        if ($options->pruneFields) {
            return;
        }

        foreach ($draft->templates as $item) {
            $removed = $this->removedFields($item);

            if ($removed !== []) {
                throw new DraftError("Template [{$item['name']}]: the draft leaves out ".implode(', ', $removed).', and building it would remove them from the template. List them to keep them, or build with --prune-fields (prune_fields in the schema.draft tool) to remove them.');
            }
        }
    }

    /**
     * The fields a template the site has would lose: its field list is replaced by the draft's.
     *
     * @param  TemplateItem  $item
     * @return list<string>
     */
    private function removedFields(array $item): array
    {
        $existing = $this->templates->find($item['name']);

        if (! $existing instanceof Template) {
            return [];
        }

        return array_values(array_diff($existing->fields->map(fn (Field $field): string => $field->name)->all(), DraftTemplates::fieldNames($item['fields'])));
    }

    /**
     * Schema files and types, rewritten when the schema changes and the site keeps them.
     *
     * @return list<array{action: string, kind: string, name: string}>
     */
    private function fileSteps(CompiledDraft $draft, DraftPlan $schema): array
    {
        if (! $schema->changesSchema()) {
            return [];
        }

        $steps = File::isDirectory($this->paths->schema) ? [['action' => DraftPlan::WRITE, 'kind' => 'schema files', 'name' => $this->paths->schema.' ('.(count($draft->fields) + count($draft->templates)).')']] : [];

        foreach ([$this->paths->phpstanTypes, $this->paths->ideHelper] as $file) {
            if (File::exists($file)) {
                $steps[] = ['action' => DraftPlan::REFRESH, 'kind' => 'types', 'name' => $file];
            }
        }

        return $steps;
    }

    /** @return list<string> notes for the developer */
    private function writeSchemaFiles(CompiledDraft $draft, DraftPlan $plan): array
    {
        if (! $plan->changesSchema()) {
            return [];
        }

        if (! File::isDirectory($this->paths->schema)) {
            return ['The site keeps no schema folder, so no schema files were written. Run php artisan lynx:schema:export to keep the schema in Git.'];
        }

        $this->exporter->exportItems(
            $this->paths->schema,
            array_values(array_filter(array_map(fn (array $f): ?Field => $this->fields->find($f['name']), $draft->fields))),
            array_values(array_filter(array_map(fn (array $t): ?Template => $this->templates->find($t['name']), $draft->templates))),
        );

        return [];
    }

    private function refreshTypes(DraftPlan $plan): void
    {
        if (! $plan->changesSchema()) {
            return;
        }

        if (File::exists($this->paths->phpstanTypes)) {
            File::put($this->paths->phpstanTypes, $this->types->render());
        }
        if (File::exists($this->paths->ideHelper)) {
            File::put($this->paths->ideHelper, $this->types->ideHelper());
        }
    }
}
