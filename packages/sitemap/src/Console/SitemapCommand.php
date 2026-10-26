<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Sitemap\Actions\ClearSitemapCache;
use LynxCms\Sitemap\Sitemap;
use LynxCms\Sitemap\SitemapPages;
use Override;

/**
 * lynx:sitemap: the sitemap's parts and their URLs; --clear drops the cache, --warm builds every file.
 */
final class SitemapCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:sitemap {--clear : Drop the cached sitemap first} {--warm : Build and cache every file now}';

    #[Override]
    protected $description = 'Show the sitemap\'s parts, and optionally rebuild its cache';

    public function handle(Sitemap $sitemap): int
    {
        if ((bool) $this->option('clear')) {
            lynx()->run(ClearSitemapCache::class);
        }

        $parts = $sitemap->parts();
        $this->line(url('/sitemap.xml'));

        foreach ($parts as $path) {
            $this->line('  '.url('/'.$path));
        }

        $this->components->info($sitemap->pageCount().' pages, '.count($parts).' '.(count($parts) === 1 ? 'part' : 'parts'));

        if ((bool) $this->option('warm')) {
            $sitemap->root();

            $this->eachStep('Caching the sitemap', $parts, function (string $path) use ($sitemap): void {
                preg_match('/^sitemap-(?:(.+)-)?(\d+)\.xml$/', $path, $match);
                $sitemap->part($match[1] ?? SitemapPages::ALL, (int) ($match[2] ?? 0));
            });

            $this->components->info('Cached.');
        }

        return self::SUCCESS;
    }
}
