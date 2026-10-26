<?php

declare(strict_types=1);

namespace LynxCms\Admin\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Users\CreateUser;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Makes someone a superuser (ADR-0079): creates their account in the app's users, or promotes the
 * account that has the email, installing Lynx first when it isn't. For a new site's first
 * superuser and for a site that lost its last one.
 *
 * @internal
 */
final class SuperuserCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:superuser {--email= : Their email} {--name= : Their name, for a new account} {--password= : For scripts; asked for otherwise}';

    #[Override]
    protected $description = 'Create a superuser, or make an existing account one';

    public function handle(ActionRunner $runner): int
    {
        $email = mb_strtolower($this->optionOrAsk('email', fn (): string => text('Email', required: true, validate: ['email' => ['required', 'email']])));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error("[{$email}] is not an email address.");

            return self::FAILURE;
        }

        $existing = Auth::getProvider()->retrieveByCredentials(['email' => $email]);

        if ($existing instanceof Authenticatable) {
            return $this->promote($runner, $existing, $email);
        }

        $name = $this->optionOrAsk('name', fn (): string => text('Name', required: true));
        $password = $this->password();

        if ($password === null) {
            $this->components->error('Give --password, or run the command without --no-interaction to be asked for it.');

            return self::FAILURE;
        }

        try {
            $id = Value::string($runner->run(CreateUser::class, ['name' => $name, 'email' => $email, 'password' => $password])->value);
            $runner->run(InstallLynx::class, ['user' => $id]);
        } catch (ValidationException $exception) {
            $this->components->error(Value::string(collect($exception->errors())->flatten()->first()));

            return self::FAILURE;
        }

        $this->components->info("{$name} <{$email}> is a superuser. Sign in at ".url(Value::string(config('lynx-admin.prefix', 'lynx'))).'.');

        return self::SUCCESS;
    }

    private function promote(ActionRunner $runner, Authenticatable $user, string $email): int
    {
        $name = Value::string(data_get($user, 'name'), $email);

        if (! confirm("{$name} <{$email}> already has an account. Make them a superuser?")) {
            return self::SUCCESS;
        }

        $runner->run(InstallLynx::class, ['user' => Value::string($user->getAuthIdentifier())]);
        $this->components->info("{$name} <{$email}> is a superuser.");

        return self::SUCCESS;
    }

    /** The password from --password, or asked twice until both match the app's rules; null when it can't ask. */
    private function password(): ?string
    {
        $given = Value::string($this->option('password'));

        if ($given !== '' || ! $this->input->isInteractive()) {
            return $given === '' ? null : $given;
        }

        while (true) {
            $password = password('Password', required: true, validate: ['password' => ['required', Password::default()]]);

            if ($password === password('Password again')) {
                return $password;
            }

            $this->components->error('The passwords differ. Try again.');
        }
    }

    /**
     * The option, trimmed, or the answer to $ask when the option is empty and someone can answer.
     *
     * @param  Closure(): string  $ask
     */
    private function optionOrAsk(string $option, Closure $ask): string
    {
        $given = trim(Value::string($this->option($option)));

        if ($given !== '' || ! $this->input->isInteractive()) {
            return $given;
        }

        return trim($ask());
    }
}
