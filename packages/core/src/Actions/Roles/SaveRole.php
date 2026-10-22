<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Roles;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Creates or updates a role and replaces its grants (ADR-0033). Superusers only.
 */
final class SaveRole extends Action
{
    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly TemplateRegistry $templates,
        private readonly PageLoader $pages,
    ) {}

    public static function name(): string
    {
        return 'roles.save';
    }

    public static function description(): string
    {
        return 'Create or update a role: its label, whether it is a superuser role, and its grants, each a permission optionally limited to templates and branches (page ids).';
    }

    /** Role management can grant anything, so it stays out of MCP and the agent. */
    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/'],
            'label' => ['nullable', 'string', 'max:255'],
            'superuser' => ['sometimes', 'boolean'],
            'grants' => ['sometimes', 'array'],
            'grants.*.permission' => ['required', 'string'],
            'grants.*.templates' => ['nullable', 'array'],
            'grants.*.templates.*' => ['string'],
            'grants.*.branches' => ['nullable', 'array'],
            'grants.*.branches.*' => ['integer'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Role name, e.g. "course-editor".')->required(),
            'label' => $schema->string(),
            'superuser' => $schema->boolean()->description('A superuser role passes every check.'),
            'grants' => $schema->array()->description('List of {"permission", "templates"?, "branches"?}; replaces the current grants.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Role
    {
        $grants = $this->grants($input->array('grants'));
        $name = (string) $input->string('name');
        $role = Role::query()->firstOrNew(['name' => $name]);
        $isNew = ! $role->exists;
        $role->label = $input->string('label') ?? ($isNew ? Str::headline($name) : $role->label);
        $role->is_superuser = $input->has('superuser') ? $input->bool('superuser') : ($isNew ? false : $role->is_superuser);
        $role->save();

        $role->grants()->delete();
        foreach ($grants as $grant) {
            $role->grants()->create($grant);
        }

        $context->changes->schema('role', ($isNew ? 'Create' : 'Update')." role {$name}", ['name' => $name, 'superuser' => $role->is_superuser, 'grants' => $grants]);

        return $role;
    }

    /**
     * @param  array<array-key, mixed>  $grants
     * @return list<array{permission: string, templates: list<string>|null, branches: list<int>|null}>
     */
    private function grants(array $grants): array
    {
        $result = [];

        foreach (array_values($grants) as $index => $grant) {
            $grant = Value::map($grant);
            $permission = Value::string($grant['permission'] ?? '');
            $templates = isset($grant['templates']) && $grant['templates'] !== [] ? Value::strings($grant['templates']) : null;
            $branches = isset($grant['branches']) && $grant['branches'] !== [] ? Value::ints($grant['branches']) : null;
            $this->assertGrant("grants.{$index}", $permission, $templates, $branches);
            $result[] = ['permission' => $permission, 'templates' => $templates, 'branches' => $branches];
        }

        $this->assertRequirements($result);

        return $result;
    }

    /**
     * Some permissions make sense only with others, in the same role so no other role's removal
     * can leave them alone.
     *
     * @param  list<array{permission: string, templates: list<string>|null, branches: list<int>|null}>  $grants
     */
    private function assertRequirements(array $grants): void
    {
        $granted = array_column($grants, 'permission');

        foreach (array_unique($granted) as $permission) {
            $missing = array_diff($this->permissions->requires($permission), $granted);

            if ($missing !== []) {
                throw ValidationException::withMessages(['grants' => "[{$permission}] needs [".implode('], [', $missing).'] in the same role.']);
            }
        }
    }

    /**
     * @param  list<string>|null  $templates
     * @param  list<int>|null  $branches
     */
    private function assertGrant(string $key, string $permission, ?array $templates, ?array $branches): void
    {
        $problem = match (true) {
            ! $this->permissions->has($permission) => "Unknown permission [{$permission}].",
            ($templates !== null || $branches !== null) && ! $this->permissions->isScoped($permission) => "[{$permission}] cannot be limited to templates or branches.",
            $templates !== null && ($missing = array_filter($templates, fn (string $name): bool => $this->templates->find($name) === null)) !== [] => 'Unknown template ['.implode(', ', $missing).'].',
            $branches !== null && ($missing = array_filter($branches, fn (int $id): bool => $this->pages->find($id) === null)) !== [] => 'Unknown page ['.implode(', ', $missing).'].',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages([$key => $problem]);
        }
    }
}
