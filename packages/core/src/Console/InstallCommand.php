<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Permissions\PermissionHolders;
use Override;

final class InstallCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:install {--migrate : Run the database migrations first} {--user= : Id of a user to make a superuser}';

    #[Override]
    protected $description = 'Install Lynx: the default language, the home page and the superuser and editor roles';

    public function handle(ActionRunner $runner, PermissionHolders $holders): int
    {
        if ($this->option('migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $user = $this->option('user');
        $result = $runner->run(InstallLynx::class, array_filter(['user' => is_string($user) && $user !== '' ? $user : null]));

        if ($result->value === false) {
            $this->components->info('Lynx is already installed.'.(is_string($user) && $user !== '' ? " User {$user} is a superuser." : ''));
        } else {
            foreach ($result->changes->summary() as $line) {
                $this->line('  '.$line);
            }

            $this->components->info('Lynx installed. Add languages with lynx()->languages->add(...) or the MCP server.');
        }

        if (! $holders->hasSuperuser()) {
            $this->components->warn('No one is a superuser yet, so no one can open the admin. Run php artisan lynx:install --user=ID to make one.');
        }

        return self::SUCCESS;
    }
}
