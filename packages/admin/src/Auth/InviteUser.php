<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Invites someone to the admin (ADR-0041): creates the host app user with an unusable password,
 * gives them roles and emails a link to choose a password. Superusers only; never an agent tool,
 * since it sends email to any address.
 *
 * @api
 */
final class InviteUser extends Action
{
    use CreatesAdminUsers;

    public function __construct(private readonly LynxUsers $users) {}

    public static function name(): string
    {
        return 'users.invite';
    }

    public static function description(): string
    {
        return 'Invite someone to the admin by email, with roles. They choose their password from the link.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Their name.')->required(),
            'email' => $schema->string()->description('Where the invitation goes; they sign in with it.')->required(),
            'roles' => $schema->array()->items($schema->string())->description('Role names to give them.'),
        ];
    }

    /** @return string the new user's id */
    public function handle(Input $input, ActionContext $context): string
    {
        ['user' => $user, 'id' => $id, 'name' => $name, 'email' => $email, 'roles' => $roles] = $this->createUser($this->users, $input, $context);

        Invitation::query()->create(['user_id' => $id, 'email' => $email, 'sent_at' => now()]);
        $context->changes->created('user', $id, "Invite {$name} <{$email}>", ['email' => $email, 'roles' => $roles]);
        $context->event(new UserInvited($id, $name, $email, Invitations::broker()->createToken($user)));

        return $id;
    }
}
