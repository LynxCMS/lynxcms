<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Laravel\Prompts\Support\Logger;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Demo\DemoSite;
use Override;

final class DemoCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:demo {--pages=0 : Extra generated course pages} {--languages=0 : Extra generated languages}';

    #[Override]
    protected $description = 'Seed the demo course site (default language + Portuguese) through the public API';

    public function handle(DemoSite $demo): int
    {
        $start = microtime(true);
        $pages = (int) $this->option('pages');

        $this->logging('Building the demo site', fn (Logger $logger) => $demo->build($pages, (int) $this->option('languages'), fn (int $done) => $logger->subLabel("{$done} of {$pages} pages")));
        $this->components->info(sprintf('Demo site ready in %.1fs. Try /courses/delivery-8/ and /pt/cursos/navegando-entrega-8/.', microtime(true) - $start));

        return self::SUCCESS;
    }
}
