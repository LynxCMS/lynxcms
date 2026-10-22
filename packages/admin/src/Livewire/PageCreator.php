<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Admin\Editor\FieldConditions;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FieldView;
use LynxCms\Admin\Editor\FieldViews;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Pages\PageUrl;
use LynxCms\Core\Support\Value;

/**
 * The New page screen: a template, the title and the template's other required fields, and an
 * optional URL name (derived from the title by default). The page is created through pages.save,
 * unpublished unless "publish now" is ticked, and opens in the editor. Required files cannot be
 * uploaded before the page exists, so a template with any always creates it unpublished.
 *
 * @api
 */
final class PageCreator extends Component
{
    #[Locked]
    public int $parentId;

    public string $template = '';

    /** @var array<string, array<string, mixed>> field name => language code => form value */
    public array $values = [];

    public string $name = '';

    public bool $publish = false;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $parentId, TemplateChoices $choices): void
    {
        $this->parentId = $parentId;
        $this->template = (string) $choices->preferred($this->parent(), $choices->for($this->parent()));
        $this->resetValues();
    }

    /** A different template asks for different fields; values of fields both share are kept. */
    public function updatedTemplate(): void
    {
        $this->resetValues();
    }

    public function create(TemplateChoices $choices, EditorForm $form): void
    {
        if (! array_key_exists($this->template, $choices->for($this->parent()))) {
            throw ValidationException::withMessages(['template' => 'Choose one of the templates offered here.']);
        }

        $fields = $form->fieldsForNewPage($this->template);
        $page = lynx()->run(SavePage::class, array_filter([
            'template' => $this->template,
            'parent' => $this->parentId,
            'names' => trim($this->name) === '' ? null : [lynx()->languages->default()->code => $this->name],
            'values' => $form->changes($fields, $form->initial($fields), $this->values),
            'status' => $this->publish && $form->requiredAfterCreate($this->template) === [] ? [] : ['unpublished'],
        ], fn (mixed $value): bool => $value !== null))->valueAs(Page::class);

        $this->redirectRoute('lynx.admin.pages.edit', ['page' => $page->id]);
    }

    public function render(TemplateChoices $choices, EditorForm $form): View
    {
        return view('lynx-admin::livewire.page-creator', [
            'parentTitle' => (new DefaultPresenter)->title($this->parent()),
            'choices' => $choices->for($this->parent()),
            ...$this->visibleFields($form),
            'inputs' => app(FieldInputs::class),
            'isSuperuser' => app(AdminAccess::class)->isSuperuser(),
            'languages' => lynx()->languages->all()->map(fn ($language): array => ['code' => Value::string($language->code), 'name' => Value::string($language->name), 'label' => $language->tabLabel()])->values()->all(),
            'url' => $this->previewUrl(),
            'defaultCode' => Value::string(lynx()->languages->default()->code),
            'addLater' => $this->template === '' ? [] : $form->requiredAfterCreate($this->template),
        ]);
    }

    /** The URL the page will get in the default language, using the rule pages.save applies. */
    private function previewUrl(): ?string
    {
        $parent = $this->parent();
        $source = trim($this->name) !== '' ? $this->name : Value::string($this->values['title'][lynx()->languages->default()->code] ?? '');
        $name = PageName::from($source);

        if ($name === '' || ! $parent->template()->hasUrl()) {
            return null;
        }

        return PageUrl::child($parent->localUrl(lynx()->languages->default()), $name);
    }

    private function resetValues(): void
    {
        if ($this->template === '') {
            $this->values = [];

            return;
        }

        $form = app(EditorForm::class);
        $this->values = array_replace($form->initial($form->fieldsForNewPage($this->template)), array_intersect_key($this->values, $form->fieldsForNewPage($this->template)));
    }

    /**
     * The new page form stays short: the title, required fields, and the fields conditions read;
     * a required_if field appears once its condition makes it required (ADR-0030).
     *
     * @return array{fields: list<FieldView>, states: array<string, array{shown: bool, required: bool}>}
     */
    private function visibleFields(EditorForm $form): array
    {
        if ($this->template === '') {
            return ['fields' => [], 'states' => []];
        }

        $conditions = app(FieldConditions::class);
        $all = lynx()->templates->fields($this->template)->all();
        $states = $conditions->states($all, $this->values);
        $watched = $conditions->watched($all);
        $isAsked = fn (FieldView $field): bool => ($states[$field->name]['shown'] ?? true)
            && ($field->name === 'title' || ($states[$field->name]['required'] ?? false) || in_array($field->name, $watched, true));

        return ['fields' => array_values(array_filter((new FieldViews)->blank($form->fieldsForNewPage($this->template)), $isAsked)), 'states' => $states];
    }

    private function parent(): Page
    {
        $parent = lynx()->pages->get($this->parentId);

        return $parent instanceof Page ? $parent : abort(404);
    }
}
