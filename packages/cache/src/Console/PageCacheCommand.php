<?php

declare(strict_types=1);

namespace LynxCms\Cache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Laravel\Prompts\Support\Logger;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\PageCacheClearer;
use LynxCms\Cache\Pages\PageCacheSettings;
use LynxCms\Cache\Pages\Warmer;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\multiselect;

/**
 * lynx:page-cache: clear, prune, warm or count stored pages, and compile the settings serve.php reads.
 */
final class PageCacheCommand extends Command
{
    use AsksInTerminal;
    use TerminalFeedback;

    /** Each flag and what it does, in the order they run. */
    private const array OPERATIONS = [
        'clear' => 'Drop every stored page',
        'prune' => 'Drop pages past their lifetime',
        'warm' => 'Render and store every public page now',
        'compile' => 'Write the settings serve.php reads',
        'stats' => 'Count stored pages per template',
    ];

    #[Override]
    protected $signature = 'lynx:page-cache
        {--clear : Drop every stored page}
        {--prune : Drop pages past their lifetime}
        {--warm : Render and store every public page now}
        {--stats : Count stored pages per template}
        {--compile : Write the settings serve.php reads}';

    #[Override]
    protected $description = 'Manage the page cache';

    public function handle(PageCacheClearer $clearer, PageCacheSettings $settings, Filesystem $files, Warmer $warmer): int
    {
        $operations = $this->operations();

        if ($operations === []) {
            $this->components->warn('Nothing to do. Pass --'.implode(', --', array_keys(self::OPERATIONS)).'.');

            return self::SUCCESS;
        }

        if (in_array('clear', $operations, true)) {
            lynx()->run(ClearPageCache::class);
            $this->components->info('Cleared.');
        }

        if (in_array('prune', $operations, true)) {
            $pruned = $clearer->prune();
            $this->components->info("Pruned {$pruned} expired ".($pruned === 1 ? 'page.' : 'pages.'));
        }

        if (in_array('warm', $operations, true)) {
            $this->warm($warmer);
        }

        if (in_array('compile', $operations, true)) {
            $file = $settings->compiledFile();
            $files->ensureDirectoryExists(dirname($file));
            $files->put($file, '<?php return '.var_export($settings->compiled(), true).';'.PHP_EOL);
            $this->components->info("Settings written to {$file}.");
        }

        if (in_array('stats', $operations, true)) {
            $this->showStats();
        }

        return self::SUCCESS;
    }

    /**
     * The flags given, or, with none and someone at the terminal, the ones they pick.
     *
     * @return list<string>
     */
    private function operations(): array
    {
        $given = array_values(array_filter(array_keys(self::OPERATIONS), fn (string $operation): bool => (bool) $this->option($operation)));

        if ($given !== [] || ! $this->canAsk()) {
            return $given;
        }

        return Value::strings(multiselect('What should be done?', self::OPERATIONS, required: true));
    }

    private function warm(Warmer $warmer): void
    {
        if (config('lynx-cache.pages.enabled') !== true) {
            $this->components->warn('The page cache is off: set LYNX_PAGE_CACHE=true to warm it.');

            return;
        }

        $report = $this->logging('Warming pages', fn (Logger $logger): array => $warmer->warm(fn (string $url) => $logger->subLabel($url)));
        $stored = $report['stored'] ?? 0;
        unset($report['stored']);
        $this->components->info("Warmed {$stored} ".($stored === 1 ? 'page.' : 'pages.'));

        foreach ($report as $reason => $count) {
            $this->line("  Not stored, {$reason}: {$count}");
        }
    }

    private function showStats(): void
    {
        $total = PageCacheEntry::query()->count();
        $this->components->info($total.' stored '.($total === 1 ? 'page' : 'pages'));
        $perTemplate = PageCacheEntry::query()->selectRaw('template, count(*) as pages')->groupBy('template')->orderBy('template')->get();
        $this->table(['Template', 'Pages'], $perTemplate->map(fn (PageCacheEntry $entry): array => [$entry->template, $entry->getAttribute('pages')])->all());
    }
}
