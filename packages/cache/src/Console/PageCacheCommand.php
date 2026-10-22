<?php

declare(strict_types=1);

namespace LynxCms\Cache\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\PageCacheClearer;
use LynxCms\Cache\Pages\PageCacheSettings;
use LynxCms\Cache\Pages\Warmer;
use Override;

/**
 * lynx:page-cache: clear, prune, warm or count stored pages, and compile the settings serve.php reads.
 */
final class PageCacheCommand extends Command
{
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
        if ((bool) $this->option('clear')) {
            lynx()->run(ClearPageCache::class);
            $this->info('Cleared.');
        }

        if ((bool) $this->option('prune')) {
            $pruned = $clearer->prune();
            $this->info("Pruned {$pruned} expired ".($pruned === 1 ? 'page.' : 'pages.'));
        }

        if ((bool) $this->option('warm')) {
            $this->warm($warmer);
        }

        if ((bool) $this->option('compile')) {
            $file = $settings->compiledFile();
            $files->ensureDirectoryExists(dirname($file));
            $files->put($file, '<?php return '.var_export($settings->compiled(), true).';'.PHP_EOL);
            $this->info("Settings written to {$file}.");
        }

        if ((bool) $this->option('stats')) {
            $this->showStats();
        }

        return self::SUCCESS;
    }

    private function warm(Warmer $warmer): void
    {
        if (config('lynx-cache.pages.enabled') !== true) {
            $this->warn('The page cache is off: set LYNX_PAGE_CACHE=true to warm it.');

            return;
        }

        $report = $warmer->warm();
        $stored = $report['stored'] ?? 0;
        unset($report['stored']);
        $this->info("Warmed {$stored} ".($stored === 1 ? 'page.' : 'pages.'));

        foreach ($report as $reason => $count) {
            $this->line("  Not stored, {$reason}: {$count}");
        }
    }

    private function showStats(): void
    {
        $total = PageCacheEntry::query()->count();
        $this->info($total.' stored '.($total === 1 ? 'page' : 'pages'));
        $perTemplate = PageCacheEntry::query()->selectRaw('template, count(*) as pages')->groupBy('template')->orderBy('template')->get();
        $this->table(['Template', 'Pages'], $perTemplate->map(fn (PageCacheEntry $entry): array => [$entry->template, $entry->getAttribute('pages')])->all());
    }
}
