<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Builder\PageForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;

/**
 * The Forms tab: forms built in the admin (published or still being built) and forms defined in
 * code, with their submissions, and "New form" for users who may build forms.
 *
 * @internal
 */
final class FormList extends Component
{
    public string $name = '';

    public string $label = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('forms.view'), 403);
    }

    public function create(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $page = lynx()->run(CreateForm::class, ['name' => trim($this->name), 'label' => trim($this->label)])->valueAs(Page::class);

        $this->redirectRoute('lynx.admin.pages.edit', ['page' => $page->id]);
    }

    public function render(): View
    {
        $counts = Submission::query()->selectRaw('form, status, count(*) as total')->groupBy('form', 'status')->get();
        $countOf = fn (string $key, ?string $status = null): int => (int) $counts->where('form', $key)->when($status !== null, fn ($rows) => $rows->where('status', $status))->sum('total');

        return view('lynx-forms::admin.livewire.form-list', [
            'rows' => [...$this->builderRows(), ...$this->codeRows()],
            'countOf' => $countOf,
            'canBuild' => app(AdminAccess::class)->allows('forms.manage'),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, kind: string, published: bool, edit: string|null}>
     */
    private function builderRows(): array
    {
        if (lynx()->templates->find(FormSchema::FORM_TEMPLATE) === null) {
            return [];
        }

        $rows = [];

        foreach (lynx()->pages->find('template='.FormSchema::FORM_TEMPLATE.', include=all, sort=title') as $page) {
            if ($page instanceof Page) {
                $rows[] = [
                    'key' => $page->name(),
                    'label' => Value::string($page->title),
                    'kind' => 'builder',
                    'published' => ! $page->hasStatus(PageStatus::UNPUBLISHED),
                    'edit' => route('lynx.admin.pages.edit', ['page' => $page->id]),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, kind: string, published: bool, edit: string|null}>
     */
    private function codeRows(): array
    {
        $rows = [];

        foreach (lynx()->forms()->all() as $key => $form) {
            if (! $form instanceof PageForm && $form instanceof Form) {
                $rows[] = ['key' => $key, 'label' => $form->text('label')->in(), 'kind' => 'code', 'published' => true, 'edit' => null];
            }
        }

        return $rows;
    }
}
