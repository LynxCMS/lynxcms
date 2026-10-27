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
use LynxCms\Admin\Editor\FieldTypeChoices;
use LynxCms\Admin\Editor\FieldUsage;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Admin\Editor\SchemaForm;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TypeDescriptions;
use LynxCms\Admin\Livewire\Concerns\EditsChoiceOptions;
use LynxCms\Admin\Livewire\Concerns\EditsItemSettings;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Setup > Fields > New field and Edit field. The settings form comes from the field type's settings
 * schema; every write goes through fields.create or fields.update, whose errors show by their
 * inputs. Name, type and translatable are fixed once the field exists (schema-admin plan).
 *
 * @api
 */
final class FieldForm extends Component
{
    use EditsChoiceOptions;
    use EditsItemSettings;
    use Notifies;

    /** The field being edited; null on the New field screen. */
    #[Locked]
    public ?string $field = null;

    public string $name = '';

    /** @var array<string, string> language code => label (ADR-0043) */
    public array $label = [];

    public string $type = 'text';

    public bool $translatable = false;

    /** @var array<string, string> language code => description */
    public array $description = [];

    /** @var array<string, string> language code => short hint next to the input */
    public array $notes = [];

    /** When a translation is empty: "inherit" shows the fallback language, "none" shows nothing. */
    public string $fallback = 'inherit';

    /** Loaded with every page instead of on first read (ADR-0067). */
    public bool $autoload = false;

    /** @var array<string, mixed> setting name => form value */
    public array $settings = [];

    /** @var list<string> text formatters in the order they run (ADR-0031) */
    public array $formatters = [];

    #[Locked]
    public ?string $status = null;

    /** Showing what deleting the field would remove, waiting for confirmation. */
    #[Locked]
    public bool $isConfirmingDelete = false;

    /** @var list<string> the dry run's change lines */
    #[Locked]
    public array $deleteSummary = [];

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, 'You may not manage templates and fields.');
    }

    public function mount(?string $field = null): void
    {
        $existing = $field === null ? null : lynx()->fields->find($field);
        $this->label = $this->description = $this->notes = LocalizedForm::toForm(null);

        if ($existing instanceof Field) {
            $config = $existing->config();
            $this->field = $existing->name;
            $this->name = $existing->name;
            $this->label = LocalizedForm::toForm($existing->labels());
            $this->type = $existing->type;
            $this->translatable = $existing->isTranslatable();
            $this->description = LocalizedForm::toForm($config['description'] ?? null);
            $this->notes = LocalizedForm::toForm($config['notes'] ?? null);
            $this->fallback = Value::string($config['fallback'] ?? 'inherit');
            $this->autoload = ($config['autoload'] ?? false) === true;
            $this->settings = app(SchemaForm::class)->toForm($this->definitions(), $config);
            $this->loadChoiceOptions($config);
            $this->formatters = Value::strings($config['formatters'] ?? []);
            $this->loadItemSettings($config);
        }
    }

    /** Livewire hook: another type has other settings. */
    public function updatedType(): void
    {
        $this->settings = [];
        $this->formatters = [];
        $this->resetItemSettings();
    }

    public function addFormatter(string $name): void
    {
        if (! in_array($name, $this->formatters, true) && array_key_exists($name, lynx()->formatters()->forType($this->type))) {
            $this->formatters[] = $name;
        }
    }

    public function removeFormatter(string $name): void
    {
        $this->formatters = array_values(array_diff($this->formatters, [$name]));
    }

    /** wire:sort handler: a formatter dropped at a 0-based position. */
    public function sortFormatter(string $name, int|string $position): void
    {
        if (in_array($name, $this->formatters, true)) {
            $order = array_values(array_diff($this->formatters, [$name]));
            array_splice($order, max(0, Value::int($position)), 0, [$name]);
            $this->formatters = $order;
        }
    }

    public function save(SchemaForm $form): void
    {
        $this->authorizeEditing();
        $this->status = null;
        $isNew = $this->field === null;
        $settings = $this->choiceSettings() + $this->itemSettings() + $form->cast($this->definitions(), $this->settings, emptyAsNull: ! $isNew) + $this->commonSettings();

        if ($isNew) {
            if (! app(FieldTypeChoices::class)->offers($this->type)) {
                throw ValidationException::withMessages(['type' => "Fields of type [{$this->type}] are created by the module that uses them."]);
            }

            lynx()->fields->add($this->name, $this->type, $this->isMultilingual() ? $this->translatable : $this->typeCanTranslate(), LocalizedForm::fromForm($this->label), array_filter($settings, fn (mixed $value): bool => $value !== null));
            $this->redirectRoute('lynx.admin.fields.edit', $this->name);

            return;
        }

        lynx()->fields->update((string) $this->field, ['label' => LocalizedForm::fromForm($this->label) ?? $this->name, 'settings' => $settings]);
        $this->notify('Saved.');
    }

    /** A dry run first, so the confirmation can say which templates and values go. */
    public function confirmDelete(): void
    {
        $this->authorizeEditing();
        $result = lynx()->fields->delete((string) $this->field, dryRun: true);
        $this->deleteSummary = $result instanceof ActionResult ? array_map(fn (Change $change): string => $change->summary, $result->changes->all()) : [];
        $this->isConfirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->isConfirmingDelete = false;
        $this->deleteSummary = [];
    }

    public function delete(): void
    {
        $this->authorizeEditing();
        lynx()->fields->delete((string) $this->field);
        $this->redirectRoute('lynx.admin.fields');
    }

    public function render(): View
    {
        $types = TypeDescriptions::forSite(lynx()->fields->types());

        return view('lynx-admin::livewire.field-form', [
            'types' => $types,
            'offeredTypes' => app(FieldTypeChoices::class)->for($types),
            'definitions' => $this->definitions(),
            'availableFormatters' => lynx()->formatters()->forType($this->type),
            // With one language the choice means nothing yet, and it can't be changed later.
            'canTranslate' => $this->isMultilingual() && $this->typeCanTranslate(),
            'usedBy' => $this->field === null ? [] : (app(FieldUsage::class)->labels()[$this->field] ?? []),
            'canEdit' => $this->canEdit(),
            'owner' => $this->field === null ? null : app(SchemaOwners::class)->ownerOfField($this->field),
            'hasItemBuilder' => $this->hasItemBuilder(),
            'subfieldChoices' => $this->hasItemBuilder() ? $this->subfieldChoices() : [],
            'subfieldTypes' => $this->hasItemBuilder() ? $this->subfieldTypes() : [],
            'typeUsage' => $this->typeUsage(),
        ]);
    }

    /**
     * Translatable is fixed once a field exists. With one language the form leaves the choice out
     * and makes fields translatable when their type allows, so adding a language later just works.
     */
    private function isMultilingual(): bool
    {
        return lynx()->languages->isMultilingual();
    }

    private function typeCanTranslate(?string $type = null): bool
    {
        return (bool) (lynx()->fields->types()[$type ?? $this->type]['translatable'] ?? false);
    }

    private function canEdit(): bool
    {
        $owners = app(SchemaOwners::class);

        return $this->field === null ? $owners->isEditingEnabled() : $owners->canEditField($this->field);
    }

    /** Read only screens refuse writes on the server too, whatever the browser sends. */
    private function authorizeEditing(): void
    {
        abort_unless($this->canEdit(), 403, 'This field cannot be changed here.');
    }

    /**
     * The type's settings for the generic form, without the ones the builder and the choices editor edit.
     *
     * @return array<string, array{type: string, description: string, enum: list<string>}>
     */
    private function definitions(): array
    {
        $definitions = app(SchemaForm::class)->definitions(Value::map(lynx()->fields->types()[$this->type]['settings'] ?? []));

        return array_diff_key($definitions, array_flip([...array_filter([$this->itemSetting()]), ...$this->choiceSettingsEditedHere()]));
    }

    /**
     * Settings every field has besides its type's: description and notes, loading with the page, and the fallback of translations.
     * Null means "not set": dropped on create, removed on update.
     *
     * @return array<string, mixed>
     */
    private function commonSettings(): array
    {
        $settings = [
            'description' => LocalizedForm::fromForm($this->description),
            'notes' => LocalizedForm::fromForm($this->notes),
            'formatters' => $this->formatters !== [] ? $this->formatters : null,
            'autoload' => $this->autoload ? true : null,
        ];

        if ($this->translatable) {
            $settings['fallback'] = $this->fallback === 'none' ? 'none' : null;
        }

        return $settings;
    }
}
