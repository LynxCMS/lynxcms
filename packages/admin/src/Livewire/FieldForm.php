<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\FieldKinds;
use LynxCms\Admin\Editor\FieldTypeChoices;
use LynxCms\Admin\Editor\FieldUsage;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Admin\Editor\SchemaForm;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Fields\FieldFormTabs;
use LynxCms\Admin\Fields\FieldTemplateOptions;
use LynxCms\Admin\Livewire\Concerns\DeletesField;
use LynxCms\Admin\Livewire\Concerns\EditsChoiceOptions;
use LynxCms\Admin\Livewire\Concerns\EditsFieldAccess;
use LynxCms\Admin\Livewire\Concerns\EditsFormatters;
use LynxCms\Admin\Livewire\Concerns\EditsItemDisplay;
use LynxCms\Admin\Livewire\Concerns\EditsItemSettings;
use LynxCms\Admin\Livewire\Concerns\MakesFieldTranslatable;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Setup > Fields > New field and Edit field, in tabs (FieldFormTabs): Basics, Settings, Access,
 * Advanced and Preview, plus a repeater's or matrix's builder and limits. Kinds and settings are in words for people (FieldKinds,
 * SettingLabels); every write goes through fields.create or fields.update, whose errors show by
 * their inputs. Name and type are fixed once the field exists (schema-admin plan); a shared field can be made translatable.
 *
 * @internal
 */
final class FieldForm extends Component
{
    use DeletesField;
    use EditsChoiceOptions;
    use EditsFieldAccess;
    use EditsFormatters;
    use EditsItemDisplay;
    use EditsItemSettings;
    use MakesFieldTranslatable;
    use Notifies;

    /** The field being edited; null on the New field screen. */
    #[Locked]
    public ?string $field = null;

    public string $name = '';

    /** @var array<string, string> language code => label (ADR-0043) */
    public array $label = [];

    public string $type = 'text';

    /** New fields start with their own text in each language; kinds that have no languages save as shared. */
    public bool $translatable = true;

    /** @var array<string, string> language code => description */
    public array $description = [];

    /** @var array<string, string> language code => short hint next to the input */
    public array $notes = [];

    /** When a translation is empty: "inherit" shows the fallback language, "none" shows nothing. */
    public string $fallback = 'inherit';

    /** Loaded with every page instead of on first read (ADR-0067). */
    public bool $autoload = false;

    /** Only the site's code writes the field, on every template (ADR-0149). */
    public bool $locked = false;

    /** @var array<string, mixed> setting name => form value */
    public array $settings = [];

    #[Locked]
    public ?string $status = null;

    /** What is typed into the Preview tab's input (FieldPreviewInput); never saved. */
    public mixed $previewValue = '';

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('schema.manage', Value::string(__('lynx-admin::fields.denied')));
    }

    public function mount(?string $field = null): void
    {
        $existing = $field === null ? null : lynx()->fields->find($field);
        $this->label = $this->description = $this->notes = $this->addLabel = LocalizedForm::toForm(null);

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
            $this->locked = ($config['locked'] ?? false) === true;
            $this->loadAccess($config);
            $this->settings = app(SchemaForm::class)->toForm($this->definitions(), $config);
            $this->loadChoiceOptions($config);
            $this->formatters = Value::strings($config['formatters'] ?? []);
            $this->loadItemSettings($config);
            $this->loadItemDisplay($config);
        }
    }

    /** Livewire hook: another type has other settings. */
    public function updatedType(): void
    {
        $this->settings = [];
        $this->formatters = [];
        $this->resetItemSettings();
        $this->addLabel = LocalizedForm::toForm(null);
        $this->previewValue = '';
    }

    public function save(SchemaForm $form): void
    {
        $this->authorizeEditing();
        $this->status = null;
        $isNew = $this->field === null;
        $settings = $this->choiceSettings() + $this->itemSettings() + $this->itemDisplaySettings() + $form->cast($this->definitions(), $this->settings, emptyAsNull: ! $isNew) + $this->commonSettings();

        if ($isNew) {
            if (! app(FieldTypeChoices::class)->offers($this->type)) {
                throw ValidationException::withMessages(['type' => __('lynx-admin::fields.module_type', ['type' => $this->type])]);
            }

            lynx()->fields->add($this->name, $this->type, $this->typeCanTranslate() && (! $this->isMultilingual() || $this->translatable), LocalizedForm::fromForm($this->label), array_filter($settings, fn (mixed $value): bool => $value !== null));
            $this->redirectRoute('lynx.admin.fields.edit', $this->name);

            return;
        }

        lynx()->fields->update((string) $this->field, ['label' => LocalizedForm::fromForm($this->label) ?? $this->name, 'settings' => $settings]);
        $this->notify(__('lynx-admin::fields.saved'));
    }

    public function render(): View
    {
        $types = lynx()->fields->types();

        $kinds = app(FieldKinds::class);
        $definitions = $this->definitions();
        $formatters = lynx()->formatters()->forType($this->type);
        $accessRoles = $this->accessRoles();
        $shownSettings = $this->hasItemBuilder() ? array_diff_key($definitions, array_flip(self::ITEM_DISPLAY_SETTINGS)) : $definitions;
        $tabs = new FieldFormTabs($this->hasItemBuilder(), $this->field === null, $shownSettings !== [] || $formatters !== [], $accessRoles !== []);

        return view('lynx-admin::livewire.field-form', [
            'title' => $this->field === null ? __('lynx-admin::fields.new') : (LocalizedForm::fromForm($this->label) === null ? $this->field : Value::string(collect($this->label)->filter()->first())),
            'tabs' => $tabs,
            'sections' => $tabs->all(),
            'kind' => $kinds->for($this->type),
            'kinds' => $this->field === null ? $kinds->grouped(array_keys(app(FieldTypeChoices::class)->for($types))) : [],
            'types' => $types,
            'offeredTypes' => app(FieldTypeChoices::class)->for($types),
            'definitions' => $definitions,
            'availableFormatters' => $formatters,
            // With one language the choice means nothing yet, and it can't be changed later.
            'canTranslate' => $this->isMultilingual() && $this->typeCanTranslate(),
            'canMakeTranslatable' => $this->canMakeTranslatable(),
            'usedBy' => $this->field === null ? [] : (app(FieldUsage::class)->labels()[$this->field] ?? []),
            'canEdit' => $this->canEdit(),
            'owner' => $this->field === null ? null : app(SchemaOwners::class)->ownerOfField($this->field),
            'hasItemBuilder' => $this->hasItemBuilder(),
            'subfieldChoices' => $this->hasItemBuilder() ? $this->subfieldChoices() : [],
            'subfieldTypes' => $this->hasItemBuilder() ? $this->subfieldTypes() : [],
            'typeUsage' => $this->typeUsage(),
            'templateOptions' => app(FieldTemplateOptions::class)->all(),
            'accessRoles' => $accessRoles,
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
        abort_unless($this->canEdit(), 403, Value::string(__('lynx-admin::fields.cannot_change')));
    }

    /**
     * The type's settings for the generic form, without the ones the builder and the choices editor edit.
     *
     * @return array<string, array{type: string, description: string, enum: list<string>, format: string}>
     */
    private function definitions(): array
    {
        $definitions = app(SchemaForm::class)->definitions(Value::map(lynx()->fields->types()[$this->type]['settings'] ?? []));

        $editedElsewhere = [...array_filter([$this->itemSetting()]), ...$this->choiceSettingsEditedHere(), ...($this->hasItemBuilder() ? ['add_label'] : [])];

        return array_diff_key($definitions, array_flip($editedElsewhere));
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
            'locked' => $this->locked ? true : null,
            'access' => $this->accessSetting(),
        ];

        if ($this->translatable) {
            $settings['fallback'] = $this->fallback === 'none' ? 'none' : null;
        }

        return $settings;
    }
}
