<?php

declare(strict_types=1);

namespace LynxCms\Cache\Console;

use Illuminate\Console\Command;
use LynxCms\Cache\Pages\ServerRules;
use LynxCms\Core\Support\Value;
use Override;

/**
 * lynx:page-cache:server: prints the rules that let a web server send stored pages itself.
 */
final class ServerRulesCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:page-cache:server {server=nginx : nginx, apache or caddy}';

    #[Override]
    protected $description = 'Print web server rules that send stored pages without PHP';

    public function handle(ServerRules $rules): int
    {
        $server = Value::string($this->argument('server'));
        $text = $rules->for($server);

        if ($text === null) {
            $this->error('Unknown server ['.$server.']: use '.implode(', ', ServerRules::SERVERS).'.');

            return self::FAILURE;
        }

        $this->line($text);

        return self::SUCCESS;
    }
}
