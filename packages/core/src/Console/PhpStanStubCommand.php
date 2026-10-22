<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\PageStub;
use Override;

final class PhpStanStubCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:phpstan-stub {--path= : File, default base_path("lynx/phpstan/Page.stub")}';

    #[Override]
    protected $description = 'Write a PHPStan stub that types $page->field reads from the schema';

    public function handle(PageStub $stub): int
    {
        $path = (string) ($this->option('path') ?: base_path('lynx/phpstan/Page.stub'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $stub->render());
        $this->components->info("Wrote {$path}. Add it to stubFiles in phpstan.neon.");

        return self::SUCCESS;
    }
}
