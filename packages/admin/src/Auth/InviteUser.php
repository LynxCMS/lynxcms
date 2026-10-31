<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Invites someone to the admin (ADR-0041): creates the host app user with an unusable password,
 * gives them roles and emails a link to choose a password. Superusers only; never an agent tool,
 * since it sends email to any address.
 */
final class InviteUser extends Action
{
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

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string'],
        ];
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
        $name = trim((string) $input->string('name'));
        $email = mb_strtolower(trim((string) $input->string('email')));

        if ($this->users->emailExists($email)) {
            throw ValidationException::withMessages(['email' => __('lynx-admin::users.invite.email_taken')]);
        }

        $user = $this->users->create($name, $email);
        $id = Value::string($user instanceof Authenticatable ? $user->getAuthIdentifier() : null);
        $roles = Value::strings($input->array('roles'));

        if ($roles !== []) {
            $context->run(AssignRoles::class, ['user' => $id, 'roles' => $roles]);
        }

        Invitation::query()->create(['user_id' => $id, 'email' => $email, 'sent_at' => now()]);
        $context->changes->created('user', $id, "Invite {$name} <{$email}>", ['email' => $email, 'roles' => $roles]);
        $context->event(new UserInvited($id, $name, $email, Invitations::broker()->createToken($user)));

        return $id;
    }
}
