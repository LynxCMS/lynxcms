<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\HookRegistry;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Lynx;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\DeleteSubmission;
use LynxCms\Forms\Actions\InstallForms;
use LynxCms\Forms\Actions\PurgeSubmissions;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Admin\AdminIntegration;
use LynxCms\Forms\Agents\GetSubmissionTool;
use LynxCms\Forms\Agents\ListSubmissionsTool;
use LynxCms\Forms\Agents\SummarizeFormTool;
use LynxCms\Forms\Builder\PageFormValidator;
use LynxCms\Forms\Console\PurgeSubmissionsCommand;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Fields\OptionsType;
use LynxCms\Forms\Listeners\NotifyRecipients;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\View\FormTag;

/**
 * Registers forms (ADR-0052): lynx()->forms(), the submission actions (and so their MCP and AI
 * tools), the forms.view and forms.manage permissions, the mail to the people who handle
 * submissions, and the daily purge of old spam.
 *
 * @api
 */
final class FormsServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-forms.php', 'lynx-forms');
        $this->app->singleton(FormRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(FieldTypeRegistry::class)->register(OptionsType::class);
        $this->app->make(ActionRegistry::class)->register(SubmitForm::class, UpdateSubmission::class, DeleteSubmission::class, PurgeSubmissions::class, InstallForms::class, CreateForm::class);
        // A form page that would make a broken form is refused, and the save rolled back (ADR-0053).
        $this->app->make(HookRegistry::class)->after(SavePage::class, fn (mixed $page) => app(PageFormValidator::class)->check($page));
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->register('forms.view', 'See and sort form submissions');
        $permissions->register('forms.manage', 'Build forms and delete submissions', requires: ['forms.view']);
        // Forms are pages under /_forms/: building forms covers the page actions there.
        $permissions->delegate(FormSchema::ROOT_TEMPLATE, 'forms.manage');
        $this->app->make(ReadToolRegistry::class)->register(ListSubmissionsTool::class, GetSubmissionTool::class, SummarizeFormTool::class);
        Lynx::macro('forms', fn (): FormRegistry => app(FormRegistry::class));
        Event::listen(FormSubmitted::class, NotifyRecipients::class);

        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-forms');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-forms');
        Blade::component('lynx-form', FormTag::class);

        // The form component needs Livewire; without it forms still work from code and actions.
        if (class_exists(Livewire::class)) {
            Livewire::component('lynx-form', FormComponent::class);
        }

        // The inbox needs the Livewire admin; without it submissions are still mailed and stored.
        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeSubmissionsCommand::class]);
            $this->publishes([self::ROOT.'/config/lynx-forms.php' => config_path('lynx-forms.php')], 'lynx-forms-config');
            $this->publishes([self::ROOT.'/lang' => $this->app->langPath('vendor/lynx-forms')], 'lynx-forms-lang');
            $this->publishes([self::ROOT.'/resources/views' => resource_path('views/vendor/lynx-forms')], 'lynx-forms-views');
            $this->publishes([self::ROOT.'/resources/css/forms.css' => resource_path('css/vendor/lynx-forms.css')], 'lynx-forms-css');
        }

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('lynx:forms:purge')->daily()->withoutOverlapping());
    }
}
