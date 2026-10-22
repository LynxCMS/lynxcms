<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Roles;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Support\Value;

/**
 * Deletes a role, its grants and who held it (ADR-0033). Superusers only.
 */
final class DeleteRole extends Action
{
    public static function name(): string
    {
        return 'roles.delete';
    }

    public static function description(): string
    {
        return 'Delete a role; users who held it lose its grants.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['role' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['role' => $schema->string()->description('Name of the role.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Role
    {
        $name = (string) $input->string('role');
        $role = Role::query()->where('name', $name)->first() ?? throw ValidationException::withMessages(['role' => "Role [{$name}] does not exist."]);
        LastSuperuser::assertKept(array_values(array_map(Value::int(...), $role->assignments()->pluck('id')->all())), 'role');

        $users = $role->assignments()->count();
        $role->delete();
        $context->changes->schema('role', "Delete role {$name} ({$users} user(s) lose it)", ['name' => $name, 'users' => $users], destructive: true);

        return $role;
    }
}
