<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\Pages\RebuildPageReferences;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Support\Value;
use Override;

/**
 * Runs page_references.rebuild, e.g. after writing field tables outside Lynx's actions.
 *
 * @internal
 */
final class PageReferencesCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:page-references:rebuild {--dry-run : List how many rows differ without changing them}';

    #[Override]
    protected $description = 'Rebuild the index of page references';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $this->working('Comparing...', fn (): ActionResult => lynx()->run(RebuildPageReferences::class, [], $dryRun));
        $totals = $result->arrayValue();

        $this->components->info(sprintf(
            '%s page(s) differed: %d row(s) added, %d removed, %d reordered%s.',
            Value::int($totals['pages'] ?? 0),
            Value::int($totals['added'] ?? 0),
            Value::int($totals['removed'] ?? 0),
            Value::int($totals['resorted'] ?? 0),
            $dryRun ? ' (dry run, nothing changed)' : '',
        ));

        return self::SUCCESS;
    }
}
