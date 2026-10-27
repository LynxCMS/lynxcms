<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Admin\Editor\EditorPanel;
use LynxCms\Admin\Editor\FieldConditions;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FieldViews;
use LynxCms\Admin\EditorTabs;
use LynxCms\Admin\Livewire\Concerns\EditsDraft;
use LynxCms\Admin\Livewire\Concerns\HoldsEditLock;
use LynxCms\Admin\Livewire\Concerns\ManagesItems;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Admin\Livewire\Concerns\ReviewsChanges;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringDefinition;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;
use Throwable;

/**
 * The page editor: every field of the page's template with language tabs and fallback previews.
 * Fields with a registered input (FieldInputs) are editable; Save sends only what changed to
 * pages.save, whose validation errors show next to their field. Review changes runs the save as a
 * dry run and lists each change before and after (ChangeReview).
 *
 * @api
 */
final class PageEditor extends Component
{
    use EditsDraft;
    use HoldsEditLock;
    use ManagesItems;
    use Notifies;
    use ReviewsChanges;

    #[Locked]
    public int $pageId;

    #[Locked]
    public ?string $error = null;

    #[Locked]
    public ?string $status = null;

    /** @var array<string, array<string, mixed>> field name => language code => form value */
    public array $values = [];

    /** @var array<string, array<string, mixed>> the values as loaded, to send only changes */
    #[Locked]
    public array $original = [];

    /** The page revision the form was loaded from; pages.save refuses to overwrite a newer one. */
    #[Locked]
    public int $revision = 0;

    /** A field the last save refused; render() opens its tab (set by exception()). */
    private ?string $errorField = null;

    /** "content", a tab of the template's layout, "texts" or "settings", kept in the URL. */
    #[Url(as: 'tab')]
    public string $tab = 'content';

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->load();

        if (app(AdminAccess::class)->allows('pages.edit', $this->page())) {
            $this->takeEditLock();
        }
    }

    /** Saves changed fields through pages.save; its ValidationException fills the error bag. */
    public function save(EditorForm $form): void
    {
        if ($this->hasDraft) {
            $this->saveDraft($form);

            return;
        }

        $this->status = null;
        $this->isReviewing = false;
        $changes = $this->changes($form);

        if ($changes === []) {
            $this->notify('No changes to save.', 'warning');

            return;
        }

        lynx()->run(SavePage::class, ['id' => $this->pageId, 'values' => $changes, 'expected_revision' => $this->revision]);
        $this->load();
        $this->notify('Saved.');
    }

    /**
     * Livewire hook: a save refused for a field on another tab opens that tab, so the error is
     * seen. Validation errors still fill the error bag as usual.
     */
    public function exception(Throwable $e, callable $stopPropagation): void
    {
        // Livewire passes hook arguments by name: they must be $e and $stopPropagation.
        $key = $e instanceof ValidationException ? (string) array_key_first($e->errors()) : '';

        if (str_starts_with($key, 'values.')) {
            $this->errorField = explode('.', $key)[1] ?? null;
        }
    }

    /**
     * The Settings tab changed the page. Names, status and parent do not touch field values, so
     * only the revision moves on and unsaved content stays; a template switch reloads the fields.
     */
    #[On('lynx-page-changed')]
    public function settingsChanged(int $revision, bool $fieldsChanged = false): void
    {
        $fieldsChanged ? $this->load() : $this->revision = $revision;
    }

    /** After a conflict: load the latest version, dropping the edits that could not be saved. */
    public function reload(): void
    {
        $this->resetErrorBag();
        $this->status = null;
        $this->load();
    }

    public function unlock(): void
    {
        $this->error = null;

        try {
            lynx()->pages->setStatus($this->pageId, remove: ['locked']);
            $this->load();
        } catch (ValidationException $exception) {
            $this->error = Value::string(Arr::first(Arr::flatten($exception->errors())), $exception->getMessage());
        }
    }

    public function render(): View
    {
        $page = $this->page();
        $layout = TemplateLayout::for($page->template());
        $fieldTabs = array_map(fn (LayoutTab $tab): string => $tab->key, $layout->tabs());

        if ($this->errorField !== null) {
            $this->tab = $layout->tabOf($this->errorField);
            $this->errorField = null;
        }

        // A template's own tab keeps its key when a module uses the same one.
        $moduleTabs = array_diff_key(app(EditorTabs::class)->for($page), array_flip($fieldTabs));

        if (! in_array($this->tab, [...$fieldTabs, ...array_keys($moduleTabs), 'texts', 'settings'], true)) {
            $this->tab = LayoutTab::CONTENT;
        }

        $fields = (new FieldViews)->for($page);
        $isFieldsTab = in_array($this->tab, $fieldTabs, true);

        return view('lynx-admin::livewire.page-editor', [
            'layout' => $layout,
            'isFieldsTab' => $isFieldsTab,
            'moduleTabs' => $moduleTabs,
            'page' => $page,
            'title' => (new DefaultPresenter)->title($page),
            'ancestors' => $this->ancestors($page),
            'fields' => $fields,
            // The panel beside the fields, unless the template lays out its own sidebar there (ADR-0063).
            'panel' => $isFieldsTab && ! $layout->hasSidebar($this->tab) ? (new EditorPanel)->for($page, $fields, $this->hasDraft) : null,
            'inputs' => app(FieldInputs::class),
            // The Texts tab: strings the template declares with @string (ADR-0047).
            'hasStrings' => array_filter(app(StringCatalog::class)->forTemplate((string) $page->template()->name), fn (StringDefinition $s): bool => ! $s->isSite()) !== [],
            'isLocked' => $page->hasStatus(PageStatus::LOCKED),
            'canEdit' => app(AdminAccess::class)->allows('pages.edit', $page) && $this->editedBy === null,
            'canTakeOver' => $this->editedBy !== null && app(AdminAccess::class)->isSuperuser(),
            'canUnlock' => app(AdminAccess::class)->allows('pages.status', $page),
            // Template and field type names are developer details.
            'isSuperuser' => app(AdminAccess::class)->isSuperuser(),
            ...$this->conditions($page),
            'review' => $this->reviewedChanges($page),
            'languages' => lynx()->languages->all()->map(fn ($language): array => ['code' => Value::string($language->code), 'name' => Value::string($language->name), 'label' => $language->tabLabel()])->values()->all(),
        ]);
    }

    /**
     * @return list<array{id: int, title: string}> from the root down to the page's parent
     */
    private function ancestors(Page $page): array
    {
        $ancestors = [];

        for ($parent = $page->parent(); $parent instanceof Page; $parent = $parent->parent()) {
            array_unshift($ancestors, ['id' => (int) $parent->id, 'title' => (new DefaultPresenter)->title($parent)]);
        }

        return $ancestors;
    }

    /**
     * Which fields show and which are required for the form as it is now, and the fields conditions read.
     *
     * @return array{states: array<string, array{shown: bool, required: bool}>, watched: list<string>}
     */
    private function conditions(Page $page): array
    {
        $fields = lynx()->templates->fields($page->template()->name)->all();
        $conditions = app(FieldConditions::class);
        $form = app(EditorForm::class);
        // Locked and hidden fields are not in the form; conditions read their saved values.
        $values = [...$form->initial($form->readOnlyFields($page->template()->name), $page), ...$this->values];

        return ['states' => $conditions->states($fields, $values), 'watched' => $conditions->watched($fields)];
    }

    /**
     * @return array<string, mixed> the pages.save "values" input for what differs from the loaded form
     */
    private function changes(EditorForm $form): array
    {
        return $form->changes($form->editableFields($this->page()->template()->name), $this->original, $this->values);
    }

    private function load(): void
    {
        $form = app(EditorForm::class);
        $page = $this->page();
        $draft = lynx()->pages->draft($this->pageId);
        $this->hasDraft = $draft !== null;
        $this->values = $this->original = $form->initial($form->editableFields($page->template()->name), $draft ?? $page);
        $this->revision = $page->revision;
    }

    private function page(): Page
    {
        $page = lynx()->pages->get($this->pageId);

        return $page instanceof Page ? $page : abort(404);
    }
}
