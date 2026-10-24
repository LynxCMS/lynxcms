<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Console\SuperuserCommand;
use LynxCms\Admin\Editor\BacklinkFilter;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FieldTypeChoices;
use LynxCms\Admin\Editor\LinkFiles;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Fields\IconType;
use LynxCms\Admin\Http\AssetController;
use LynxCms\Admin\Icons\IconSet;
use LynxCms\Admin\Livewire\AccountSecurity;
use LynxCms\Admin\Livewire\AdminSearch;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\FieldList;
use LynxCms\Admin\Livewire\IconPicker;
use LynxCms\Admin\Livewire\LanguageForm;
use LynxCms\Admin\Livewire\LanguageList;
use LynxCms\Admin\Livewire\LinkDialog;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageHistory;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Admin\Livewire\PageRevisions;
use LynxCms\Admin\Livewire\PageSchedule;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Admin\Livewire\PageStrings;
use LynxCms\Admin\Livewire\PageTableInput;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Admin\Livewire\RoleForm;
use LynxCms\Admin\Livewire\RoleList;
use LynxCms\Admin\Livewire\TemplateForm;
use LynxCms\Admin\Livewire\TemplateList;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Admin\Tree\TreeMarkers;
use LynxCms\Admin\Users\CreateUser;
use LynxCms\Admin\Users\EloquentUsers;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Admin\View\TailwindMergeMacros;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Http\Middleware\WithoutPageVisibility;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Support\Value;

/**
 * Registers the Livewire admin: config, views, routes, components and the theme stub.
 *
 * @api
 */
final class AdminServiceProvider extends ServiceProvider
{
    /** Gate checked on every admin route and Livewire request. Define it in the app to restrict access. */
    public const string GATE = 'accessLynxAdmin';

    /** Whether the admin's sidebar is open: set by the browser, read to render the next page. */
    public const string SIDEBAR_COOKIE = 'lynx_sidebar';

    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        TailwindMergeMacros::register($this->app);
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-admin.php', 'lynx-admin');
        AdminAuth::configure();
        $this->app->singleton(FieldInputs::class);
        $this->app->singleton(AdminBranches::class);
        $this->app->singleton(EditorLinks::class);
        $this->app->singleton(EditorTabs::class);
        $this->app->singleton(LinkFiles::class);
        $this->app->singleton(TemplateChoices::class);
        $this->app->singleton(FieldTypeChoices::class);
        $this->app->singleton(BacklinkFilter::class);
        // The trash page's template is Lynx's own (ADR-0035): a system template.
        $this->app->singleton(SchemaOwners::class, function (): SchemaOwners {
            $owners = new SchemaOwners;
            $owners->register('Lynx', [Trash::TEMPLATE]);

            return $owners;
        });
        $this->app->bindIf(LynxUsers::class, EloquentUsers::class);
        $this->app->singleton(IconSet::class);
        $this->app->singleton(TreeMarkers::class);
        $this->app->singleton(Navigation::class, fn (): Navigation => $this->navigation());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-admin');
        // Written by the sidebar's script, so it is plain text, not one Laravel encrypted.
        EncryptCookies::except(self::SIDEBAR_COOKIE);
        Blade::anonymousComponentPath(self::ROOT.'/resources/views/components', 'lynx-admin');
        // Admin components' updates keep the admin routes' WithoutPageVisibility (ADR-0062).
        Livewire::addPersistentMiddleware([WithoutPageVisibility::class]);
        Livewire::component('lynx-admin.page-tree', PageTree::class);
        Livewire::component('lynx-admin.icon-picker', IconPicker::class);
        // The icon field type: an icon name from the admin's set (docs/deeper/custom-field-types.md).
        $this->app->make(FieldTypeRegistry::class)->register(IconType::class);
        $this->app->make(ActionRegistry::class)->register(CreateUser::class);
        Livewire::component('lynx-admin.search', AdminSearch::class);
        Livewire::component('lynx-admin.language-list', LanguageList::class);
        Livewire::component('lynx-admin.account-security', AccountSecurity::class);
        Livewire::component('lynx-admin.page-strings', PageStrings::class);
        Livewire::component('lynx-admin.language-form', LanguageForm::class);
        Livewire::component('lynx-admin.page-editor', PageEditor::class);
        Livewire::component('lynx-admin.page-creator', PageCreator::class);
        Livewire::component('lynx-admin.page-picker', PagePicker::class);
        Livewire::component('lynx-admin.page-table', PageTableInput::class);
        Livewire::component('lynx-admin.link-dialog', LinkDialog::class);
        Livewire::component('lynx-admin.page-settings', PageSettings::class);
        Livewire::component('lynx-admin.page-schedule', PageSchedule::class);
        Livewire::component('lynx-admin.page-history', PageHistory::class);
        Livewire::component('lynx-admin.page-revisions', PageRevisions::class);
        Livewire::component('lynx-admin.field-list', FieldList::class);
        Livewire::component('lynx-admin.field-form', FieldForm::class);
        Livewire::component('lynx-admin.template-list', TemplateList::class);
        Livewire::component('lynx-admin.template-form', TemplateForm::class);
        Livewire::component('lynx-admin.role-list', RoleList::class);
        Livewire::component('lynx-admin.role-form', RoleForm::class);
        Livewire::component('lynx-admin.user-roles', UserRoles::class);
        $this->defineDefaultGate();
        AdminAuth::boot();

        if (AdminAuth::managesAccounts()) {
            $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        }

        Admin::routes(self::ROOT.'/routes/admin.php');
        $this->assetRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([SuperuserCommand::class]);
            $this->publishes([self::ROOT.'/config/lynx-admin.php' => config_path('lynx-admin.php')], 'lynx-admin-config');
            $this->publishes([self::ROOT.'/resources/css/theme.stub.css' => public_path('vendor/lynx-admin/theme.css')], 'lynx-admin-theme');
        }
    }

    /** The core screens: content first, then the Settings and Access sections, closed by default. */
    private function navigation(): Navigation
    {
        $canManageSchema = fn (): bool => app(AdminAccess::class)->allows('schema.manage');

        return (new Navigation)
            ->group(Navigation::SETTINGS, 'Settings', order: 80, icon: 'settings')
            ->group(Navigation::ACCESS, 'Access', order: 90, icon: 'shield-check')
            ->add('Pages', 'lynx.admin.pages', order: 10, icon: 'folder-tree')
            ->add('Site texts', 'lynx.admin.site-texts', order: 30, visible: fn (): bool => app(AdminAccess::class)->allows('pages.edit'), icon: 'type')
            ->add('Fields', 'lynx.admin.fields', order: 10, visible: $canManageSchema, icon: 'text-cursor-input', group: Navigation::SETTINGS)
            ->add('Templates', 'lynx.admin.templates', order: 20, visible: $canManageSchema, icon: 'layout-template', group: Navigation::SETTINGS)
            ->add('Languages', 'lynx.admin.languages', order: 50, visible: $canManageSchema, icon: 'languages', group: Navigation::SETTINGS)
            ->add('Roles', 'lynx.admin.roles', order: 10, visible: RoleList::currentUserIsSuperuser(...), icon: 'shield-check', group: Navigation::ACCESS)
            ->add('Users', 'lynx.admin.users', order: 20, visible: UserRoles::currentUserIsSuperuser(...), icon: 'users', group: Navigation::ACCESS);
    }

    /** The stylesheet and script are public: the sign-in screens load them before anyone is signed in. */
    private function assetRoutes(): void
    {
        Route::middleware('web')->prefix(Value::string(config('lynx-admin.prefix')))->name('lynx.admin.')->group(function (): void {
            Route::get('assets/admin.css', AssetController::class)->name('assets.css');
            Route::get('assets/admin.js', [AssetController::class, 'script'])->name('assets.js');
        });
    }

    /**
     * The default rule: holding admin.access (ADR-0033). With debugging on, a refusal on a site
     * where no one is a superuser yet says how to make one (ADR-0078). Apps can define their own
     * rule instead.
     */
    private function defineDefaultGate(): void
    {
        if (Gate::has(self::GATE)) {
            return;
        }

        Gate::define(self::GATE, function (Authenticatable $user): Response {
            if (Gate::forUser($user)->allows('lynx.admin.access')) {
                return Response::allow();
            }

            $isFirstRun = Value::bool(config('app.debug')) && ! $this->app->make(PermissionHolders::class)->hasSuperuser();

            return Response::deny($isFirstRun ? 'No one is a superuser yet. Run php artisan lynx:superuser to make one.' : null);
        });
    }
}
