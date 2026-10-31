<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Support\Facades\Gate;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Builder\PageForms;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormValidator;
use LynxCms\Forms\Livewire\FormComponent;

/**
 * A form as visitors will see it, inside the admin: unpublished forms too. Sending checks the
 * answers and then stops: nothing is stored or mailed.
 *
 * @internal
 */
final class PreviewForm extends FormComponent
{
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('forms.view'), 403);
    }

    public function definition(): Form
    {
        return app(PageForms::class)->find($this->form, includeUnpublished: true) ?? parent::definition();
    }

    public function submit(): void
    {
        app(FormValidator::class)->make($this->definition(), $this->answers())->validate();
        $this->problem = Value::string(__('lynx-forms::admin.preview.done'));
    }
}
