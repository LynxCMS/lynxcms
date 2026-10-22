<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Demo\DemoSite;
use Override;

final class DemoCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:demo {--pages=0 : Extra generated course pages} {--languages=0 : Extra generated languages}';

    #[Override]
    protected $description = 'Seed the demo course site (default language + Portuguese) through the public API';

    public function handle(DemoSite $demo): int
    {
        $start = microtime(true);
        $demo->build((int) $this->option('pages'), (int) $this->option('languages'), fn (int $i) => $this->line("  {$i} pages..."));
        $this->components->info(sprintf('Demo site ready in %.1fs. Try /courses/delivery-8/ and /pt/cursos/navegando-entrega-8/.', microtime(true) - $start));

        return self::SUCCESS;
    }
}
