<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Roles;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Core\Support\Value;

/**
 * Sets the roles of a host app user, replacing the ones they had (ADR-0033). Superusers only.
 */
final class AssignRoles extends Action
{
    public static function name(): string
    {
        return 'roles.assign';
    }

    public static function description(): string
    {
        return 'Set the roles of a user (by id), replacing the roles they had.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['user' => ['required'], 'roles' => ['present', 'array'], 'roles.*' => ['string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'user' => $schema->string()->description('Id of the user.')->required(),
            'roles' => $schema->array()->items($schema->string())->description('Role names; an empty list removes every role.')->required(),
        ];
    }

    /**
     * @return list<string> the user's role names
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $user = Value::string($input->get('user'));
        $names = Value::strings($input->array('roles'));
        $roles = Role::query()->whereIn('name', $names)->get();

        if ($roles->count() !== count(array_unique($names))) {
            throw ValidationException::withMessages(['roles' => 'Unknown role ['.implode(', ', array_diff($names, Value::strings($roles->pluck('name')->all()))).'].']);
        }

        $removed = RoleAssignment::query()->where('user_id', $user)->whereNotIn('role_id', $roles->modelKeys())->pluck('id')->all();
        LastSuperuser::assertKept(array_values(array_map(Value::int(...), $removed)), 'roles');

        RoleAssignment::query()->whereKey($removed)->delete();
        foreach ($roles as $role) {
            $role->assign($user, $context->actor->kind.($context->actor->id !== null ? ':'.$context->actor->id : ''));
        }

        $context->changes->schema('role', "Set the roles of user {$user} to [".implode(', ', $names).']', ['user' => $user, 'roles' => $names]);

        return $names;
    }
}
