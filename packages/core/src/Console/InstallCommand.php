<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Laravel\Prompts\Elements\Element;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;

/**
 * Installs Lynx, then makes sure someone can open the admin: --user promotes an account (by id or
 * email), and without one, when no one is a superuser, it offers lynx:superuser (ADR-0079).
 */
final class InstallCommand extends Command
{
    use AsksInTerminal;
    use TerminalFeedback;

    /** The admin package's command that creates a superuser. */
    private const string SUPERUSER_COMMAND = 'lynx:superuser';

    #[Override]
    protected $signature = 'lynx:install {--migrate : Run the database migrations first} {--user= : Id or email of an existing account to make a superuser}';

    #[Override]
    protected $description = 'Install Lynx: the default language, the home page and the superuser and editor roles';

    public function handle(ActionRunner $runner, PermissionHolders $holders): int
    {
        if ($this->option('migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $given = Value::string($this->option('user'));
        $user = $this->userId($given);

        if ($given !== '' && $user === null) {
            $this->components->error("No user has the email [{$given}]. Run php artisan ".self::SUPERUSER_COMMAND.' to create the account.');

            return self::FAILURE;
        }

        $result = $runner->run(InstallLynx::class, array_filter(['user' => $user]));

        if ($result->value === false) {
            $this->components->info('Lynx is already installed.'.($user !== null ? " User {$given} is a superuser." : ''));
        } else {
            $this->box('Lynx installed', [
                Element::bulletedList($result->changes->summary()),
                'Add languages with lynx()->languages->add(...) or the MCP server.',
            ]);
        }

        return $holders->hasSuperuser() ? self::SUCCESS : $this->offerSuperuser();
    }

    /** An id as given, or the id of the account with the email; null for none. */
    private function userId(string $given): ?string
    {
        if ($given === '' || ! str_contains($given, '@')) {
            return $given === '' ? null : $given;
        }

        $user = Auth::getProvider()->retrieveByCredentials(['email' => mb_strtolower(trim($given))]);

        return $user === null ? null : Value::string($user->getAuthIdentifier());
    }

    private function offerSuperuser(): int
    {
        $canCreate = $this->getApplication()?->has(self::SUPERUSER_COMMAND) ?? false;

        if ($canCreate && $this->canAsk() && confirm('No one is a superuser yet. Create one now?')) {
            return $this->call(self::SUPERUSER_COMMAND);
        }

        $this->components->warn('No one is a superuser yet, so no one can open the admin. Run php artisan '
            .($canCreate ? self::SUPERUSER_COMMAND.' to create one, or ' : '').'php artisan lynx:install --user=EMAIL to make an existing account one.');

        return self::SUCCESS;
    }
}
