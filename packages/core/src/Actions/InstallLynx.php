<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\SetNotFoundPage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\NotFoundPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Creates what every Lynx site has: the default language (code "default", name "Default",
 * no prefix, ADR-0006), the root page, and a hidden "Page not found" page shown for URLs that match
 * nothing (ADR-0085), unless someone already chose one or none. Safe to run twice.
 */
final class InstallLynx extends Action
{
    public const string NOT_FOUND_TEMPLATE = 'not-found';

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly RootPage $root,
        private readonly NotFoundPage $notFound,
        private readonly TemplateRegistry $templates,
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
    /** The role people who sign up get: no grants, so the website and never the admin (ADR-0073). */
    public const string GUEST_ROLE = 'guest';

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
        $installed = $this->ensureNotFoundPage($context) || $installed;
        $installed = $this->ensureRoles($context) || $installed;

        if ($input->get('user') !== null) {
            $user = Value::string($input->get('user'));
            $roles = Role::query()->whereKey(RoleAssignment::query()->where('user_id', $user)->pluck('role_id'))->pluck('name')->all();
            $context->run(AssignRoles::class, ['user' => $user, 'roles' => array_values(array_unique([...Value::strings($roles), 'superuser']))]);
        }

        return $installed;
    }

    /** The superuser, editor and guest roles (ADR-0033, ADR-0073), unless roles with those names exist. */
    /**
     * A page under the home page, with a template of its own that only has a title. A system page,
     * like the trash: it cannot be deleted or moved, stays last among its siblings and out of
     * listings, search and the sitemap; its content is edited like any other page's.
     */
    private function ensureNotFoundPage(ActionContext $context): bool
    {
        if ($this->notFound->isChosen()) {
            return false;
        }

        if ($this->templates->find(self::NOT_FOUND_TEMPLATE) === null) {
            $context->run(CreateTemplate::class, ['name' => self::NOT_FOUND_TEMPLATE, 'label' => 'Page not found', 'fields' => [['name' => 'title', 'required' => true]]]);
        }

        $page = $context->run(SavePage::class, [
            'template' => self::NOT_FOUND_TEMPLATE,
            'parent' => $this->root->id(),
            'values' => ['title' => 'Page not found'],
            'names' => [(string) $this->languages->default()->code => self::NOT_FOUND_TEMPLATE],
            'status' => ['hidden', 'system'],
        ]);
        $context->run(SetNotFoundPage::class, ['page' => $page instanceof Page ? $page->id : null]);

        return true;
    }

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

        if (! Role::query()->where('name', self::GUEST_ROLE)->exists()) {
            $context->run(SaveRole::class, ['name' => self::GUEST_ROLE, 'label' => 'Guest']);
            $created = true;
        }

        return $created;
    }
}
