<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaSync;
use Throwable;

/**
 * Builds a parsed draft (ADR-0116): its languages first, then the fields, templates and pages,
 * compiled once those languages exist, so labels may use a language the same draft adds or a
 * default language it renames. Everything runs in one transaction: a failure anywhere, or a dry
 * run, leaves the languages as they were.
 *
 * @phpstan-import-type LanguageItem from SchemaSync
 *
 * @internal
 */
final readonly class DraftRunner
{
    public function __construct(
        private DraftLanguages $languages,
        private DraftCompiler $compiler,
        private DraftBuilder $builder,
        private SchemaSync $sync,
        private SchemaExporter $exporter,
        private DraftPaths $paths,
        private ActionRunner $runner,
    ) {}

    /**
     * @param  array<array-key, mixed>  $draft  the parsed draft.yaml
     *
     * @throws DraftError
     */
    public function run(array $draft, DraftOptions $options): DraftResult
    {
        $this->compiler->assertKeys($draft);
        $items = $this->languages->compile($draft['languages'] ?? null);
        $steps = $this->languages->steps($items);
        unset($draft['languages']);

        if ($steps === []) {
            return $this->builder->build($this->compiler->compile($draft), $options);
        }

        DB::beginTransaction();

        try {
            $result = $this->buildWithLanguages($draft, $items, $steps, $options);
            $options->dryRun || $result->hasErrors() ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        } finally {
            // The registries cache what the rolled back languages changed.
            $this->runner->flushCaches();
        }

        if (! $options->dryRun && ! $result->hasErrors() && $options->writeFiles && File::isDirectory($this->paths->schema)) {
            // A renamed code changes the labels of every field and template, so every file is rewritten.
            $this->exporter->export($this->paths->schema);
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $draft
     * @param  list<LanguageItem>  $items
     * @param  list<array{action: string, kind: string, name: string}>  $steps
     */
    private function buildWithLanguages(array $draft, array $items, array $steps, DraftOptions $options): DraftResult
    {
        $languages = $this->sync->applyLanguages($items);

        if ($languages->hasErrors()) {
            return new DraftResult(new DraftPlan($steps), $languages);
        }

        $built = $this->builder->build($this->compiler->compile($draft), $options);
        array_unshift($built->sync->changes, ...$languages->changes);
        $files = $options->writeFiles && File::isDirectory($this->paths->schema)
            ? [['action' => DraftPlan::WRITE, 'kind' => 'schema files', 'name' => $this->paths->schema.' (all)']]
            : [];
        $rest = array_filter($built->plan->steps, fn (array $step): bool => $step['kind'] !== 'schema files');

        return new DraftResult(new DraftPlan([...$steps, ...array_values($rest), ...$files]), $built->sync, $built->notes);
    }
}
