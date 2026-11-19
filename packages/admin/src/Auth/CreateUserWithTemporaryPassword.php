<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Creates a user with roles and a temporary password, without sending email (ADR-0126). The
 * password comes back once, for the superuser to pass on; the activity log never holds it.
 * Superusers only; never an agent tool.
 *
 * @api
 */
final class CreateUserWithTemporaryPassword extends Action
{
    use CreatesAdminUsers;

    public function __construct(private readonly LynxUsers $users, private readonly TemporaryPasswords $passwords) {}

    public static function name(): string
    {
        return 'users.create_with_temporary_password';
    }

    public static function description(): string
    {
        return 'Create a user with roles and a temporary password, which they replace when they first sign in.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Their name.')->required(),
            'email' => $schema->string()->description('They sign in with it. Nothing is sent to it.')->required(),
            'roles' => $schema->array()->items($schema->string())->description('Role names to give them.'),
        ];
    }

    /** @return array{user: string, password: string, expires_at: string} */
    public function handle(Input $input, ActionContext $context): array
    {
        ['user' => $user, 'id' => $id, 'name' => $name, 'email' => $email, 'roles' => $roles] = $this->createUser($this->users, $input, $context);
        $given = $this->passwords->give($user);
        $context->changes->created('user', $id, "Create {$name} <{$email}> with a temporary password", ['email' => $email, 'roles' => $roles]);

        return ['user' => $id, ...$given];
    }
}
