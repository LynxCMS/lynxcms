<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Core\Support\Value;

/**
 * Creates what every Lynx site has: the default language (code "default", name "Default",
 * no prefix, ADR-0006) and the root page. Safe to run twice.
 */
final class InstallLynx extends Action
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly RootPage $root,
    ) {}

    public static function name(): string
    {
        return 'lynx.install';
    }

    public static function description(): string
    {
        return 'Install Lynx: create the default language and the root page if they do not exist.';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    /** The editor role's grants: everyday page work on every template and branch. */
    public const array EDITOR_PERMISSIONS = ['admin.access', 'pages.view', 'pages.edit', 'pages.create', 'pages.status', 'pages.move', 'pages.publish'];

    public static function rules(): array
    {
        return ['user' => ['nullable']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['user' => $schema->string()->description('Id of a user to make a superuser.')];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $installed = false;

        if (! $this->languages->hasDefault()) {
            $context->run(CreateLanguage::class, ['code' => 'default', 'name' => 'Default', 'prefix' => '']);
            $installed = true;
        }

        $installed = $this->root->ensure($context) || $installed;
        $installed = $this->ensureRoles($context) || $installed;

        if ($input->get('user') !== null) {
            $user = Value::string($input->get('user'));
            $roles = Role::query()->whereKey(RoleAssignment::query()->where('user_id', $user)->pluck('role_id'))->pluck('name')->all();
            $context->run(AssignRoles::class, ['user' => $user, 'roles' => array_values(array_unique([...Value::strings($roles), 'superuser']))]);
        }

        return $installed;
    }

    /** The superuser and editor roles (ADR-0033), unless roles with those names exist. */
    private function ensureRoles(ActionContext $context): bool
    {
        $created = false;

        if (! Role::query()->where('name', 'superuser')->exists()) {
            $context->run(SaveRole::class, ['name' => 'superuser', 'label' => 'Superuser', 'superuser' => true]);
            $created = true;
        }

        if (! Role::query()->where('name', 'editor')->exists()) {
            $context->run(SaveRole::class, ['name' => 'editor', 'label' => 'Editor', 'grants' => array_map(fn (string $permission): array => ['permission' => $permission], self::EDITOR_PERMISSIONS)]);
            $created = true;
        }

        return $created;
    }
}
