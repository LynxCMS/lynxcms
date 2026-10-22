<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Query\PageFinder;
use Override;

/**
 * Times typical selectors for the performance spike (phase 9).
 */
final class BenchCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:bench {--lang=pt : Language whose fallback chain is used} {--runs=5}';

    #[Override]
    protected $description = 'Time typical selectors and page loads (performance spike)';

    public function handle(PageFinder $finder, LanguageContext $context, PageLoader $loader): int
    {
        $selectors = [
            'listing' => 'template=course, limit=20',
            'shared filter' => 'template=course, price<200, limit=20',
            'shared equality' => 'template=course, price=500, limit=20',
            'translatable filter' => 'template=course, title%=course 1, limit=20',
            'translatable filter, rare' => 'template=course, title%=course 4242, limit=20',
            'translatable equality' => 'template=course, title="Generated course 4242", limit=20',
            'translatable range' => 'template=course, title>"Generated course 9", limit=20',
            'translatable sort' => 'template=course, sort=title, limit=20',
            'translatable sort, deep page' => 'template=course, sort=-title, start=5000, limit=20',
            'count' => 'template=course, price>100',
        ];

        $rows = [];

        $context->with((string) $this->option('lang'), function () use ($selectors, $finder, $loader, &$rows): void {
            foreach ($selectors as $label => $selector) {
                $times = [];

                for ($i = 0; $i < (int) $this->option('runs'); $i++) {
                    $loader->flush();
                    $start = hrtime(true);
                    str_starts_with($label, 'count') ? $finder->count($selector) : $finder->find($selector);
                    $times[] = (hrtime(true) - $start) / 1e6;
                }

                sort($times);
                $rows[] = [$label, $selector, sprintf('%.1f', $times[intdiv(count($times), 2)]), sprintf('%.1f', $times === [] ? 0 : max($times))];
            }
        });

        $this->table(['Case', 'Selector', 'Median ms', 'Max ms'], $rows);

        return self::SUCCESS;
    }
}
