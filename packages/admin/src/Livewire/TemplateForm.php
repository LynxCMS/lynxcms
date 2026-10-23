<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Livewire\Concerns\EditsTemplateLayout;
use LynxCms\Admin\Livewire\Concerns\ManagesTemplateFamily;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\TemplateUsage;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\EditorSettings;
use LynxCms\Core\Templates\FieldDisplay;
use LynxCms\Core\Templates\TemplateField;

/**
 * Setup > Templates > New template and Edit template. The Fields section holds the ordered field
 * list with per template overrides (label, description, required, searchable, show_if, required_if) and saves it
 * in one templates.update, whose errors show above the list. Family and delete: ManagesTemplateFamily.
 *
 * @api
 */
final class TemplateForm extends Component
{
    use EditsTemplateLayout;
    use ManagesTemplateFamily;
    use Notifies;

    private const array OVERRIDES = ['show_if', 'required_if', 'icon'];

    /** Overrides editors read, per language (ADR-0043). */
    private const array LOCALIZED_OVERRIDES = ['label', 'description', 'notes', 'placeholder'];

    /** Yes or no overrides, stored only when set. */
    private const array FLAGS = ['required', 'searchable'];

    /** Pages listed under Pages; the count covers the rest. */
    private const int PAGES_LISTED = 50;

    /** The template being edited; null on the New template screen. */
    #[Locked]
    public ?string $template = null;

    public string $name = '';

    /** @var array<string, string> language code => label (ADR-0043) */
    public array $label = [];

    /** New template: copy the fields of this template. */
    public string $from = '';

    /** @var list<string> field names in order */
    public array $order = [];

    /** @var array<string, array{label: array<string, string>, description: array<string, string>, notes: array<string, string>, placeholder: array<string, string>, required: bool, searchable: bool, show_if: string, required_if: string, icon: string, editor: string, width: string}> */
    public array $rows = [];

    /** Field chosen in the Add field select. */
    public string $newField = '';

    /** @var list<string> fields removed since the last save, for the orphans note */
    #[Locked]
    public array $removed = [];

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, 'You may not manage templates and fields.');
    }

    public function mount(?string $template = null): void
    {
        $existing = $template === null ? null : lynx()->templates->find($template);
        $this->label = LocalizedForm::toForm(null);

        if ($existing instanceof Template) {
            $this->template = (string) $existing->name;
            $this->name = (string) $existing->name;
            $this->label = LocalizedForm::toForm($existing->labels());
            $this->loadFields();
            $this->loadFamily($existing);
        }
    }

    public function create(): void
    {
        $this->authorizeEditing();
        lynx()->templates->add($this->name, label: LocalizedForm::fromForm($this->label), from: $this->from !== '' ? $this->from : null);
        $this->redirectRoute('lynx.admin.templates.edit', $this->name);
    }

    public function saveLabel(): void
    {
        $this->authorizeEditing();
        lynx()->templates->update((string) $this->template, ['label' => LocalizedForm::fromForm($this->label) ?? $this->name]);
        $this->notify('Saved.');
    }

    public function addField(): void
    {
        if ($this->newField === '' || in_array($this->newField, $this->order, true) || ! lynx()->fields->find($this->newField) instanceof Field) {
            return;
        }

        $this->order[] = $this->newField;
        $this->placeNewField($this->newField);
        $this->rows[$this->newField] = $this->row([]);
        $this->removed = array_values(array_diff($this->removed, [$this->newField]));
        $this->newField = '';
    }

    public function removeField(string $field): void
    {
        $this->order = array_values(array_diff($this->order, [$field]));
        $this->forgetField($field);
        unset($this->rows[$field]);
        $this->removed[] = $field;
    }

    public function saveFields(): void
    {
        $this->authorizeEditing();
        $this->status = null;
        lynx()->templates->update((string) $this->template, ['tabs' => $this->layoutTabs(), 'fields' => $this->layoutFieldList($this->settingsOf(...))]);
        $this->loadFields();
        $this->notify('Saved.');
    }

    public function render(): View
    {
        $fields = lynx()->fields->all();

        return view('lynx-admin::livewire.template-form', [
            'templates' => lynx()->templates->all()->map(fn (Template $template): string => (string) $template->name)->sort()->values()->all(),
            'available' => $fields->reject(fn (Field $field): bool => in_array($field->name, $this->order, true))->map(fn (Field $field): string => $field->name)->sort()->values()->all(),
            'fieldInfo' => $fields->mapWithKeys(fn (Field $field): array => [$field->name => ['label' => $field->label, 'type' => $field->type]])->all(),
            'canEdit' => $this->canEdit(),
            'owner' => $this->template === null ? null : app(SchemaOwners::class)->ownerOfTemplate($this->template),
            ...$this->usage(),
        ]);
    }

    /**
     * The pages that keep the template from being deleted, trashed ones too.
     *
     * @return array{pageCount: int, pagesUsing: list<array{id: int, title: string, url: string, trashed: bool}>}
     */
    private function usage(): array
    {
        $template = $this->template === null ? null : lynx()->templates->find($this->template);

        if ($template === null) {
            return ['pageCount' => 0, 'pagesUsing' => []];
        }

        $usage = app(TemplateUsage::class);

        return ['pageCount' => $usage->count($template), 'pagesUsing' => $usage->pages($template, self::PAGES_LISTED)];
    }

    private function canEdit(): bool
    {
        $owners = app(SchemaOwners::class);

        return $this->template === null ? $owners->isEditingEnabled() : $owners->canEditTemplate($this->template);
    }

    /** Read only screens refuse writes on the server too, whatever the browser sends. */
    private function authorizeEditing(): void
    {
        abort_unless($this->canEdit(), 403, 'This template cannot be changed here.');
    }

    private function loadFields(): void
    {
        $fields = lynx()->templates->fields((string) $this->template);
        $this->order = array_values(array_map(Value::string(...), $fields->keys()->all()));
        $this->rows = $fields->map(fn (TemplateField $field): array => $this->row($field->settings))->all();
        $this->loadLayout(lynx()->templates->get((string) $this->template));
        $this->removed = [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{label: array<string, string>, description: array<string, string>, notes: array<string, string>, placeholder: array<string, string>, required: bool, searchable: bool, show_if: string, required_if: string, icon: string, editor: string, width: string}
     */
    private function row(array $settings): array
    {
        return [
            'label' => LocalizedForm::toForm($settings['label'] ?? null),
            'description' => LocalizedForm::toForm($settings['description'] ?? null),
            'notes' => LocalizedForm::toForm($settings['notes'] ?? null),
            'placeholder' => LocalizedForm::toForm($settings['placeholder'] ?? null),
            'required' => (bool) ($settings['required'] ?? false),
            'searchable' => (bool) ($settings['searchable'] ?? false),
            'show_if' => Value::string($settings['show_if'] ?? ''),
            'required_if' => Value::string($settings['required_if'] ?? ''),
            'icon' => Value::string($settings['icon'] ?? ''),
            'editor' => Value::string($settings['editor'] ?? FieldDisplay::Open->value),
            'width' => (string) Value::int($settings['width'] ?? EditorSettings::FULL_WIDTH),
        ];
    }

    /**
     * The overrides set for a field; empty ones are left out so the field's own values apply.
     *
     * @return array<string, mixed>
     */
    private function settingsOf(string $name): array
    {
        $row = Value::map($this->rows[$name] ?? []);
        $settings = [];

        foreach (self::OVERRIDES as $key) {
            $value = trim(Value::string($row[$key] ?? ''));

            if ($value !== '') {
                $settings[$key] = $value;
            }
        }

        foreach (self::LOCALIZED_OVERRIDES as $key) {
            $value = LocalizedForm::fromForm(Value::array($row[$key] ?? []));

            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        foreach (self::FLAGS as $flag) {
            if ((bool) ($row[$flag] ?? false)) {
                $settings[$flag] = true;
            }
        }

        return $settings + $this->editorSettingsOf($row);
    }

    /**
     * How the field shows in the page editor (ADR-0063), left out at the defaults.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function editorSettingsOf(array $row): array
    {
        $editor = Value::string($row['editor'] ?? '');
        $width = Value::int($row['width'] ?? EditorSettings::FULL_WIDTH);

        return array_filter([
            'editor' => in_array($editor, ['', FieldDisplay::Open->value], true) ? null : $editor,
            'width' => $width === EditorSettings::FULL_WIDTH ? null : $width,
        ], fn (mixed $value): bool => $value !== null);
    }
}
