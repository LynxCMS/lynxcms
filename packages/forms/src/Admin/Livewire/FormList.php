<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\DuplicateForm;
use LynxCms\Forms\Admin\Livewire\Concerns\ImportsForms;
use LynxCms\Forms\Builder\PageForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;

/**
 * The Forms tab: forms built in the admin (published or still being built) and forms defined in
 * code, with their submissions, and "New form", "Import form" and each form's Export for users
 * who may build forms (ADR-0146).
 *
 * @internal
 */
final class FormList extends Component
{
    use ImportsForms;

    public string $name = '';

    public string $label = '';

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('forms.view');
    }

    public function create(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        // A name is a key in lowercase: "Contact" is taken as "contact", and the box shows it.
        $this->name = Str::lower(trim($this->name));
        $page = lynx()->run(CreateForm::class, ['name' => $this->name, 'label' => trim($this->label)])->valueAs(Page::class);

        $this->redirectRoute('lynx.admin.pages.edit', ['page' => $page->id]);
    }

    /** Copies a builder form (forms.duplicate) and opens the copy in the editor. */
    public function duplicate(string $form): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $copy = Value::map(lynx()->run(DuplicateForm::class, ['form' => $form])->value);
        session()->flash('lynx-notify', ['message' => Value::string(__('lynx-forms::admin.list.duplicated')), 'type' => 'success']);

        $this->redirectRoute('lynx.admin.pages.edit', ['page' => Value::int($copy['page'] ?? 0)]);
    }

    public function render(): View
    {
        $counts = Submission::query()->selectRaw('form, status, count(*) as total')->groupBy('form', 'status')->get();
        $countOf = fn (string $key, ?string $status = null): int => Value::int($counts->where('form', $key)->when($status !== null, fn ($rows) => $rows->where('status', $status))->sum('total'));

        return view('lynx-forms::admin.livewire.form-list', [
            'rows' => [...$this->builderRows(), ...$this->codeRows()],
            'countOf' => $countOf,
            'canBuild' => app(AdminAccess::class)->allows('forms.manage'),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, kind: string, published: bool, edit: string|null, structure: string|null}>
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
                    'key' => FormSchema::key($page),
                    'label' => Value::string($page->title),
                    'kind' => 'builder',
                    'published' => ! $page->hasStatus(PageStatus::UNPUBLISHED),
                    'edit' => route('lynx.admin.pages.edit', ['page' => $page->id]),
                    'structure' => route('lynx.admin.forms.structure', FormSchema::key($page)),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, kind: string, published: bool, edit: string|null, structure: string|null}>
     */
    private function codeRows(): array
    {
        $rows = [];

        foreach (lynx()->forms()->all() as $key => $form) {
            if (! $form instanceof PageForm && $form instanceof Form) {
                $rows[] = ['key' => $key, 'label' => $form->text('label')->in(), 'kind' => 'code', 'published' => true, 'edit' => null, 'structure' => null];
            }
        }

        return $rows;
    }
}
