<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Laravel\Prompts\Elements\Element;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Schema\SchemaSync;
use Override;

use function Laravel\Prompts\confirm;

final class SchemaApplyCommand extends Command
{
    use AsksInTerminal;
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:schema:apply {--path= : Directory, default base_path("lynx/schema")} {--force : Also delete languages, fields and templates missing from the files} {--dry-run : Show the changes without applying them}';

    #[Override]
    protected $description = 'Apply schema YAML files through the normal actions (idempotent)';

    public function handle(SchemaSync $sync): int
    {
        $path = (string) ($this->option('path') ?: base_path('lynx/schema'));
        $isForced = (bool) $this->option('force');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isForced && ! $isDryRun && $this->canAsk() && ! $this->confirmForced($sync, $path)) {
            $this->components->warn('Nothing was applied.');

            return self::FAILURE;
        }

        $result = $sync->apply($path, $isForced, $isDryRun);

        foreach ($result->changes as $line) {
            $this->line('  '.$line);
        }

        foreach ($result->skipped as $line) {
            $this->line('  <fg=yellow>skipped</> '.$line);
        }

        foreach ($result->errors as $line) {
            $this->line('  <fg=red>error</> '.$line);
        }

        if ($result->hasErrors()) {
            $this->components->error('Nothing was applied.');

            return self::FAILURE;
        }

        $result->isEmpty()
            ? $this->components->info('The database already matches the files.')
            : $this->components->info($result->applied ? 'Applied.' : 'Dry run: nothing was changed.');

        return self::SUCCESS;
    }

    /**
     * --force deletes what the files no longer have, so in a terminal the dry run is shown first.
     * A dry run with errors or no changes needs no answer: the real run reports it the same way.
     */
    private function confirmForced(SchemaSync $sync, string $path): bool
    {
        $preview = $sync->apply($path, force: true, dryRun: true);

        if ($preview->hasErrors() || $preview->isEmpty()) {
            return true;
        }

        $this->box('Schema changes', [
            Element::bulletedList($preview->changes),
            'With --force, languages, fields and templates missing from the files are deleted.',
        ], type: 'warning');

        return confirm('Apply these changes?', default: false);
    }
}
