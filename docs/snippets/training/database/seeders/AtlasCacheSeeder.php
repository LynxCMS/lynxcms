<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LynxCms\Cache\Actions\SavePageCacheRules;

/**
 * Which pages the page cache keeps, and what a change clears (chapter 10). Staff with
 * page_cache.manage can change the same rules under Settings › Page cache.
 */
final class AtlasCacheSeeder extends Seeder
{
    public function run(): void
    {
        // #region rules
        // A change clears the page and every page above it: a new session date changes its course
        // page, the catalogue's "Next" line and the home page's course cards.
        $up = ['self', 'parents'];

        lynx()->run(SavePageCacheRules::class, ['templates' => [
            'home' => ['cache' => true, 'clear' => ['self']],
            'courses' => ['cache' => true, 'clear' => ['self'], 'parameters' => ['level', 'q', 'page']],
            'course' => ['cache' => true, 'clear' => $up],
            'session' => ['cache' => true, 'clear' => $up],
            'instructors' => ['cache' => true, 'clear' => ['self']],
            'instructor' => ['cache' => true, 'clear' => [...$up, 'home']],
            'locations' => ['cache' => true, 'clear' => ['self']],
            'location' => ['cache' => true, 'clear' => $up],
            'posts' => ['cache' => true, 'clear' => ['self']],
            'post' => ['cache' => true, 'clear' => $up],
            'page' => ['cache' => true, 'clear' => ['self']],
            // Materials are personal: each member sees their own, so they are never stored.
            'materials' => ['cache' => false],
        ]]);
        // #endregion rules
    }
}
