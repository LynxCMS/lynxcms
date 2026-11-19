<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Turns off a user's two-factor authentication (ADR-0128), for someone who lost their phone and
 * their recovery codes. When the admin requires it, they set it up again on their next visit.
 * Superusers only (the console is one, for a superuser locked out); never an agent tool.
 *
 * @api
 */
final class ResetTwoFactor extends Action
{
    public function __construct(private readonly DisableTwoFactorAuthentication $disable) {}

    public static function name(): string
    {
        return 'users.reset_two_factor';
    }

    public static function description(): string
    {
        return 'Turn off a user\'s two-factor authentication, so they set it up again.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['user' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['user' => $schema->string()->description('Id of the user.')->required()];
    }

    /** @return string the user's name */
    public function handle(Input $input, ActionContext $context): string
    {
        $id = Value::string($input->get('user'));
        $user = Auth::getProvider()->retrieveById($id);

        if (! $user instanceof Model || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            throw ValidationException::withMessages(['user' => __('lynx-admin::users.two_factor.unsupported')]);
        }

        ($this->disable)($user);
        $context->changes->updated('user', $id, 'Reset two-factor authentication of '.Value::string($user->getAttribute('email')), []);

        return Value::string($user->getAttribute('name'), $id);
    }
}
