<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\ImportForm;
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
    use WithFileUploads;

    /** The largest form export Import form takes, in KB. */
    private const int IMPORT_MAX_KB = 1024;

    public string $name = '';

    public string $label = '';

    /** A form export picked in Import form's file input. */
    public ?TemporaryUploadedFile $importFile = null;

    /** Import form's name for the new form, when the site has the export's. */
    public string $importName = '';

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('forms.view');
    }

    public function create(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $page = lynx()->run(CreateForm::class, ['name' => trim($this->name), 'label' => trim($this->label)])->valueAs(Page::class);

        $this->redirectRoute('lynx.admin.pages.edit', ['page' => $page->id]);
    }

    /**
     * Creates the form in the file through forms.import, then opens it in the editor, saying which
     * languages' texts were left out.
     */
    public function import(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $this->validate(['importFile' => ['required', 'file', 'max:'.self::IMPORT_MAX_KB]]);

        try {
            $form = json_decode((string) $this->importFile?->get(), true, 512, JSON_THROW_ON_ERROR);
            $input = ['form' => $form] + array_filter(['name' => trim($this->importName)]);
            $result = Value::map(lynx()->run(ImportForm::class, $input)->value);
        } catch (JsonException) {
            throw ValidationException::withMessages(['importFile' => Value::string(__('lynx-forms::admin.list.import_not_json'))]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([isset($e->errors()['name']) ? 'importName' : 'importFile' => $e->validator->errors()->first()]);
        }

        $dropped = Value::strings($result['dropped_languages'] ?? []);
        session()->flash('lynx-notify', $dropped === []
            ? ['message' => Value::string(__('lynx-forms::admin.list.imported')), 'type' => 'success']
            : ['message' => Value::string(__('lynx-forms::admin.list.imported_dropped', ['languages' => implode(', ', $dropped)])), 'type' => 'warning']);
        $this->redirectRoute('lynx.admin.pages.edit', ['page' => Value::int($result['page'] ?? 0)]);
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
