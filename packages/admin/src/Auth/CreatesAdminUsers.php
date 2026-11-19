<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Validation\ValidationException;
use LogicException;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * What users.invite and users.create_with_temporary_password share: the input, a new user with an unusable password,
 * and their roles. A user needs at least one role: without one, they could not open the admin.
 *
 * @internal
 */
trait CreatesAdminUsers
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ];
    }

    /** @return array{user: CanResetPassword&Authenticatable, id: string, name: string, email: string, roles: list<string>} */
    private function createUser(LynxUsers $users, Input $input, ActionContext $context): array
    {
        $name = trim((string) $input->string('name'));
        $email = mb_strtolower(trim((string) $input->string('email')));

        if ($users->emailExists($email)) {
            throw ValidationException::withMessages(['email' => __('lynx-admin::users.invite.email_taken')]);
        }

        $user = $users->create($name, $email);
        $user = $user instanceof Authenticatable ? $user : throw new LogicException('New users must be able to sign in.');
        $id = Value::string($user->getAuthIdentifier());
        $roles = Value::strings($input->array('roles'));
        $context->run(AssignRoles::class, ['user' => $id, 'roles' => $roles]);

        return ['user' => $user, 'id' => $id, 'name' => $name, 'email' => $email, 'roles' => $roles];
    }
}
