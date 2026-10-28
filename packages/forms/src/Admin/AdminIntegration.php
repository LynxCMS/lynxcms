<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FieldKinds;
use LynxCms\Admin\Editor\FieldTypeChoices;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\EditorLinks;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Admin\Http\DownloadSubmissionFile;
use LynxCms\Forms\Admin\Http\ExportSubmissions;
use LynxCms\Forms\Admin\Http\ShowFormPreview;
use LynxCms\Forms\Admin\Http\ShowFormResults;
use LynxCms\Forms\Admin\Http\ShowSubmission;
use LynxCms\Forms\Admin\Livewire\FormList;
use LynxCms\Forms\Admin\Livewire\Inbox;
use LynxCms\Forms\Admin\Livewire\PreviewForm;
use LynxCms\Forms\Admin\Livewire\SubmissionView;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;

/**
 * Adds the submissions inbox to the admin, with the number of new submissions beside "Forms".
 * Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 15;

    public static function register(): void
    {
        app(FieldInputs::class)->register('options', 'lynx-forms::inputs.options', OptionsConverter::class);
        // Only the form builder's schema creates options fields; editors pick choice instead.
        app(FieldTypeChoices::class)->exclude('options');
        app(FieldKinds::class)->register('options', 'Options for visitors', 'Choices', 'list-checks', 'A list visitors pick from, such as the answers to a form question.');
        Livewire::component('lynx-forms.inbox', Inbox::class);
        Livewire::component('lynx-forms.submission', SubmissionView::class);
        Livewire::component('lynx-forms.list', FormList::class);
        Livewire::component('lynx-forms.preview', PreviewForm::class);
        self::registerBuilder();

        $canSee = fn (): bool => app(AdminAccess::class)->allows('forms.view');
        app(Navigation::class)->add('Forms', 'lynx.admin.forms', order: self::NAVIGATION_ORDER, visible: $canSee, icon: 'inbox',
            badge: fn (): int => Submission::query()->where('status', 'new')->count());

        Admin::routes(function (): void {
            Admin::screen('forms', 'lynx-forms::admin.index', 'forms.view')->name('forms');
            Admin::screen('forms/builder', 'lynx-forms::admin.builder', 'forms.view')->name('forms.builder');
            Route::get('forms/preview/{form}', ShowFormPreview::class)->name('forms.preview');
            Route::get('forms/export/{form}', ExportSubmissions::class)->name('forms.export');
            Route::get('forms/results/{form}', ShowFormResults::class)->name('forms.results');
            Route::get('forms/{submission}', ShowSubmission::class)->name('forms.show');
            Route::get('forms/{submission}/files/{file}', DownloadSubmissionFile::class)->name('forms.file');
        });
    }

    /**
     * Form pages are edited in the page editor, with a preview link; the /_forms/ branch stays
     * out of the page tree, and its root opens the Forms tab (ADR-0053).
     */
    private static function registerBuilder(): void
    {
        app(AdminBranches::class)->register(FormSchema::ROOT_TEMPLATE, fn (Page $page, Page $root): ?string => $page->id === $root->id ? route('lynx.admin.forms.builder') : null);
        app(EditorLinks::class)->add(fn (Page $page): ?array => $page->template()->name === FormSchema::FORM_TEMPLATE
            ? ['label' => 'Preview form', 'url' => route('lynx.admin.forms.preview', FormSchema::key($page))]
            : null);
        app(TemplateChoices::class)->exclude(FormSchema::ROOT_TEMPLATE, FormSchema::FORM_TEMPLATE);
        app(SchemaOwners::class)->register('Forms', array_keys(FormSchema::templates()), array_values(array_diff(array_keys(FormSchema::fields()), [FormSchema::PICKER])));
    }
}
