<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Auth;
use Laravel\Prompts\Elements\Element;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Console\Concerns\AddsModules;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Install\ModuleCatalog;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;

/**
 * Installs Lynx, after adding the modules picked or given with --modules (AddsModules), then, when lynxcms/admin is installed, makes sure someone can open the admin:
 * --user promotes an account (by id or email), and without one, when no one is a superuser, it
 * offers lynx:superuser (ADR-0079). --user works without the admin too, for MCP and the agent.
 *
 * @internal
 */
final class InstallCommand extends Command
{
    use AddsModules;
    use AsksInTerminal;
    use TerminalFeedback;

    /** The admin package's command that creates a superuser. */
    private const string SUPERUSER_COMMAND = 'lynx:superuser';

    #[Override]
    protected $signature = 'lynx:install {--migrate : Run the database migrations first} {--user= : Id or email of an existing account to make a superuser} {--modules= : Modules to add with Composer, comma separated, such as admin,menus}';

    #[Override]
    protected $description = 'Install Lynx: the default language, the home page and the superuser and editor roles';

    public function handle(ActionRunner $runner, PermissionHolders $holders, ModuleCatalog $modules, Composer $composer): int
    {
        $chosen = $this->chosenModules($modules);
        $requirements = $chosen === null ? [] : $modules->requirements($chosen);

        if ($chosen === null || $requirements !== []) {
            return $chosen === null ? self::FAILURE : $this->addModules($requirements, $composer);
        }

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
                'Add languages with lynx()->languages->add(\'pt\', \'Português\')'.($this->hasAdmin() ? ' or under Settings › Languages in the admin.' : '.'),
            ]);
        }

        // Without lynxcms/admin there is no admin to open, so nobody needs to be a superuser.
        return $holders->hasSuperuser() || ! $this->hasAdmin() ? self::SUCCESS : $this->offerSuperuser();
    }

    /** Whether lynxcms/admin is installed: it brings the command that creates a superuser. */
    private function hasAdmin(): bool
    {
        return $this->getApplication()?->has(self::SUPERUSER_COMMAND) ?? false;
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
        if ($this->canAsk() && confirm('No one is a superuser yet. Create one now?')) {
            return $this->call(self::SUPERUSER_COMMAND);
        }

        $this->components->warn('No one is a superuser yet, so no one can open the admin. Run php artisan '.self::SUPERUSER_COMMAND
            .' to create one, or php artisan lynx:install --user=EMAIL to make an existing account one.');

        return self::SUCCESS;
    }
}
