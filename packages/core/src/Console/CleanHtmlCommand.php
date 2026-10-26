<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\Fields\CleanHtmlValues;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Support\Value;
use Override;

/** Runs fields.clean_html, e.g. once after upgrading from a version without the HTML sanitizer. */
final class CleanHtmlCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:clean-html {--field= : One html field, default every html field} {--dry-run : Show what would change without writing}';

    #[Override]
    protected $description = 'Clean stored values of html fields the way a save cleans them';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $field = Value::nullableString($this->option('field'));
        $result = $this->working('Cleaning html values...', fn (): ActionResult => lynx()->run(CleanHtmlValues::class, array_filter(['field' => $field]), dryRun: $isDryRun));

        foreach ($result->changes->summary() as $line) {
            $this->line('  '.$line);
        }

        $count = array_sum(array_map(fn (Change $change): int => Value::int($change->data['values'] ?? 0), $result->changes->all()));

        match (true) {
            $count === 0 => $this->components->info('Every html value is already clean.'),
            $isDryRun => $this->components->info("Dry run: {$count} value(s) would change, nothing was written."),
            default => $this->components->info("Cleaned {$count} value(s)."),
        };

        return self::SUCCESS;
    }
}
