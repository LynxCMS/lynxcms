<?php

declare(strict_types=1);

namespace LynxCms\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Admin\AdminPath;
use LynxCms\Admin\InvalidAdminPath;
use LynxCms\Core\Pages\ActivePageUrls;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use Override;

/**
 * Shows the admin's address, or moves the admin (ADR-0122): to a chosen path such as "admin",
 * on a site that needs /lynx for a page, or to a random one that is hard to guess. It writes
 * LYNX_ADMIN_PREFIX to .env, as key:generate writes APP_KEY, and refuses a path a page uses.
 *
 * @internal
 */
final class AdminPathCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:admin-path
        {path? : The new path, e.g. "admin" or "team/cms"}
        {--random : Move the admin to a random path that is hard to guess}';

    #[Override]
    protected $description = 'Show the admin\'s address, or move the admin to another path';

    public function handle(ActivePageUrls $urls, PageLoader $pages): int
    {
        $path = $this->argument('path');

        if (is_string($path) && $this->option('random')) {
            $this->components->error('Give a path, or --random, not both.');

            return self::FAILURE;
        }

        if (! is_string($path) && ! $this->option('random')) {
            $this->components->info('The admin is at '.url(AdminPath::current()).'.');

            return self::SUCCESS;
        }

        return $this->move(is_string($path) ? $path : AdminPath::random(), $urls, $pages);
    }

    private function move(string $path, ActivePageUrls $urls, PageLoader $pages): int
    {
        try {
            $path = AdminPath::validate($path);
        } catch (InvalidAdminPath $invalid) {
            $this->components->error($invalid->getMessage());

            return self::FAILURE;
        }

        $taken = $urls->under($path);

        if ($taken !== []) {
            $this->refuseTaken($path, $taken, $pages);

            return self::FAILURE;
        }

        $this->writeEnvironment($path);
        $this->components->info('The admin is now at '.url($path).'. Bookmarks and links to the old address stop working.');
        $this->warnWhenCached();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $taken  URL => page id
     */
    private function refuseTaken(string $path, array $taken, PageLoader $pages): void
    {
        $this->components->error("Pages use /{$path}: the admin would hide them from visitors.");

        foreach ($taken as $url => $pageId) {
            $this->components->bulletList(["{$url} (".Value::string($pages->find($pageId)?->title).')']);
        }
    }

    /** Replace the setting in .env, or add it when .env does not have it yet. */
    private function writeEnvironment(string $path): void
    {
        $file = $this->laravel->environmentFilePath();
        $contents = File::exists($file) ? File::get($file) : '';
        $line = AdminPath::ENV.'='.$path;
        $pattern = '/^'.AdminPath::ENV.'=.*$/m';

        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, $line, $contents)
            : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";

        File::put($file, $contents);
    }

    private function warnWhenCached(): void
    {
        if ($this->laravel->configurationIsCached()) {
            $this->components->warn('The configuration is cached: run php artisan config:cache (or config:clear) for the new path to take effect.');
        }
    }
}
