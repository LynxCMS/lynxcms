<?php

declare(strict_types=1);

namespace LynxCms\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use LynxCms\Admin\Auth\ResetTwoFactor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Support\Value;
use Override;

/**
 * lynx:two-factor:reset: turns off a user's two-factor authentication from the server, for a
 * superuser who lost their phone and their recovery codes (ADR-0128).
 *
 * @internal
 */
final class ResetTwoFactorCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:two-factor:reset {email : The user\'s email}';

    #[Override]
    protected $description = 'Turn off a user\'s two-factor authentication, so they set it up again';

    public function handle(ActionRunner $runner): int
    {
        $email = mb_strtolower(trim(Value::string($this->argument('email'))));
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);

        if (! $user instanceof Authenticatable) {
            $this->error("No user has the email {$email}.");

            return self::FAILURE;
        }

        $name = Value::string($runner->run(ResetTwoFactor::class, ['user' => Value::string($user->getAuthIdentifier())])->value);
        $this->info("Two-factor authentication reset for {$name}. They set it up again when they next open the admin.");

        return self::SUCCESS;
    }
}
