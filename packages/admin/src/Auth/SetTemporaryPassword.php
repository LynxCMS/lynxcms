<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Replaces a user's password with a temporary one (ADR-0126), for someone who forgot theirs on a
 * site that sends no email. Their old password stops working at once. Not for the superuser
 * running it, who changes their own on the account screen. Superusers only; never an agent tool.
 *
 * @api
 */
final class SetTemporaryPassword extends Action
{
    public function __construct(private readonly TemporaryPasswords $passwords) {}

    public static function name(): string
    {
        return 'users.temporary_password';
    }

    public static function description(): string
    {
        return 'Replace a user\'s password with a temporary one, which they change when they next sign in.';
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

    /** @return array{password: string, expires_at: string} */
    public function handle(Input $input, ActionContext $context): array
    {
        $id = Value::string($input->get('user'));
        $user = $id === Value::string($context->actor->id) ? null : Auth::getProvider()->retrieveById($id);

        if (! $user instanceof Authenticatable) {
            throw ValidationException::withMessages(['user' => __('lynx-admin::users.temporary.not_for_you')]);
        }

        $given = $this->passwords->give($user);
        $context->changes->updated('user', $id, 'Give '.Value::string(data_get($user, 'email')).' a temporary password', []);

        return $given;
    }
}
