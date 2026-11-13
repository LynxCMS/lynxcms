<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Elements\Element;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Schema\Draft\CompiledDraft;
use LynxCms\Core\Schema\Draft\DraftBuilder;
use LynxCms\Core\Schema\Draft\DraftCompiler;
use LynxCms\Core\Schema\Draft\DraftError;
use LynxCms\Core\Schema\Draft\DraftJsonSchema;
use LynxCms\Core\Schema\Draft\DraftOptions;
use LynxCms\Core\Schema\Draft\DraftReader;
use LynxCms\Core\Schema\Draft\DraftResult;
use LynxCms\Core\Schema\Draft\DraftWriter;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;

/**
 * Builds a draft of templates and fields (ADR-0116): lynx/draft.yaml in a short syntax becomes the
 * site's schema, a starting view for each new template with a URL, and the schema files and types
 * the site keeps. In a terminal it shows what changes and asks first.
 */
final class DraftCommand extends Command
{
    use AsksInTerminal;
    use TerminalFeedback;

    private const string STUB = __DIR__.'/../../resources/stubs/draft.yaml';

    #[Override]
    protected $signature = 'lynx:draft
        {path? : The draft, default lynx/draft.yaml}
        {--dry-run : Show what would change, and change nothing}
        {--no-views : Write no views for new templates}
        {--force-views : Replace the views of new templates that exist}
        {--prune-fields : Remove the fields a draft leaves out of a template the site has}
        {--stub : Write a starting draft to the path}
        {--from-site : Write the site\'s own templates and fields as a draft to the path}
        {--json-schema : Write draft.schema.json beside the draft, so editors check and complete it}';

    #[Override]
    protected $description = 'Build templates and fields from a short YAML draft, with starting views';

    public function handle(DraftReader $reader, DraftCompiler $compiler, DraftBuilder $builder, DraftWriter $writer, DraftJsonSchema $schema): int
    {
        $path = Value::nullableString($this->argument('path')) ?? base_path('lynx/draft.yaml');

        if ($this->option('json-schema')) {
            return $this->writeJsonSchema(dirname($path).'/draft.schema.json', $schema);
        }

        if ($this->option('stub') || $this->option('from-site')) {
            return $this->writeDraft($path, $this->option('stub') ? (string) File::get(self::STUB) : $writer->write());
        }

        try {
            return $this->build($builder, $compiler->compile($reader->read($path)), $this->draftOptions());
        } catch (DraftError $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function build(DraftBuilder $builder, CompiledDraft $draft, DraftOptions $options): int
    {
        if (! $options->dryRun && $this->canAsk() && ! $this->confirmBuild($builder, $draft, $options)) {
            $this->components->warn('Nothing was built.');

            return self::FAILURE;
        }

        return $this->report($builder->build($draft, $options), $options);
    }

    private function draftOptions(): DraftOptions
    {
        return new DraftOptions(
            dryRun: (bool) $this->option('dry-run'),
            views: ! $this->option('no-views'),
            forceViews: (bool) $this->option('force-views'),
            pruneFields: (bool) $this->option('prune-fields'),
        );
    }

    private function report(DraftResult $result, DraftOptions $options): int
    {
        foreach ($result->plan->lines() as $line) {
            $this->line('  '.$line);
        }

        foreach ($result->sync->errors as $line) {
            $this->line('  <fg=red>error</> '.$line);
        }

        if ($result->hasErrors()) {
            $this->components->error('Nothing was built.');

            return self::FAILURE;
        }

        $this->components->info(match (true) {
            ! $result->plan->changesSchema() => 'The site already matches the draft.',
            $options->dryRun => 'Dry run: nothing was changed.',
            default => 'Built.',
        });
        array_map(fn (string $note) => $this->components->warn($note), $result->notes);

        return self::SUCCESS;
    }

    /** The plan in a box, then the question. A plan with errors or nothing to do needs no answer. */
    private function confirmBuild(DraftBuilder $builder, CompiledDraft $draft, DraftOptions $options): bool
    {
        $preview = $builder->build($draft, $options->asDryRun());

        if ($preview->hasErrors() || ! $preview->plan->changesSchema()) {
            return true;
        }

        $this->box('Draft', [Element::bulletedList($preview->plan->lines())], type: 'info');

        return confirm('Build this draft?', default: true);
    }

    /** The schema is generated, so it is replaced each time: after installing a module, for its types. */
    private function writeJsonSchema(string $path, DraftJsonSchema $schema): int
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $schema->toJson());
        $this->components->info("Wrote {$path}. Drafts beside it that start with the yaml-language-server line are checked as you type.");

        return self::SUCCESS;
    }

    private function writeDraft(string $path, string $contents): int
    {
        if (File::exists($path)) {
            $this->components->error("A draft exists at {$path}. Move it, or give another path.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->components->info("Wrote {$path}. Edit it, then run php artisan lynx:draft --dry-run.");

        return self::SUCCESS;
    }
}
