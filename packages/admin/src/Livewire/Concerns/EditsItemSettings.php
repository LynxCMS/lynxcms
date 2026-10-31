<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Admin\Editor\FieldTypeChoices;
use LynxCms\Admin\Editor\ItemSettingsForm;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Fields\Items\ItemSettingsRules;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Fields\Types\RepeaterType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * The field form's builder for a repeater's fields and a matrix's block types: add, sort and
 * remove types and fields, or create a field on the spot. A list is "fields" (the repeater's)
 * or "types.{index}" (a block type's). Nothing is stored until Save, except a field created on
 * the spot, which fields.create stores at once.
 *
 * @internal
 */
trait EditsItemSettings
{
    /** @var list<array{key: string, slug: string, label: array<string, string>, item_label: string, fields: list<array<string, mixed>>}> */
    public array $itemTypes = [];

    /** @var list<array<string, mixed>> the repeater's fields */
    public array $itemFields = [];

    /** @var array<string, string> type key => slug of the types stored already, which never change (ADR-0045) */
    #[Locked]
    public array $savedSlugs = [];

    /** @var array{list: string, name: string, type: string, label: array<string, string>, translatable: bool} the Create field dialog */
    public array $newSubfield = ['list' => '', 'name' => '', 'type' => 'text', 'label' => [], 'translatable' => false];

    public function addType(): void
    {
        $this->itemTypes[] = ItemSettingsForm::blankType();
    }

    public function removeType(string $key): void
    {
        $this->itemTypes = array_values(array_filter($this->itemTypes, fn (array $type): bool => $type['key'] !== $key));
    }

    /** wire:sort handler: a type dropped at a 0-based position. */
    public function sortType(string $key, int|string $position): void
    {
        $moving = array_values(array_filter($this->itemTypes, fn (array $type): bool => $type['key'] === $key));

        if ($moving === []) {
            return;
        }

        $order = array_values(array_filter($this->itemTypes, fn (array $type): bool => $type['key'] !== $key));
        array_splice($order, max(0, Value::int($position)), 0, $moving);
        $this->itemTypes = $order;
    }

    public function addSubfield(string $list, string $name): void
    {
        $fields = $this->subfieldList($list);
        $field = lynx()->fields->find($name);

        if ($fields === null || ! $field instanceof Field || in_array($name, array_column($fields, 'name'), true)) {
            return;
        }

        if (ItemSettingsRules::canBeSubfield($field->fieldType())) {
            $this->setSubfieldList($list, [...$fields, ItemSettingsForm::subfieldToForm(['name' => $name])]);
        }
    }

    public function removeSubfield(string $list, string $name): void
    {
        $fields = $this->subfieldList($list) ?? [];
        $this->setSubfieldList($list, array_values(array_filter($fields, fn (array $row): bool => $row['name'] !== $name)));
    }

    /** wire:sort handler: "{list}:{field name}" dropped at a 0-based position. */
    public function sortSubfield(string $item, int|string $position): void
    {
        [$list, $name] = array_pad(explode(':', $item, 2), 2, '');
        $fields = $this->subfieldList($list) ?? [];
        $moving = array_values(array_filter($fields, fn (array $row): bool => $row['name'] === $name));

        if ($moving === []) {
            return;
        }

        $order = array_values(array_filter($fields, fn (array $row): bool => $row['name'] !== $name));
        array_splice($order, max(0, Value::int($position)), 0, $moving);
        $this->setSubfieldList($list, $order);
    }

    /** Opens the Create field dialog for a list. */
    public function startSubfield(string $list): void
    {
        $this->resetErrorBag();
        $this->newSubfield = ['list' => $list, 'name' => '', 'type' => 'text', 'label' => LocalizedForm::toForm(null), 'translatable' => false];
        $this->dispatch('open-dialog-new-subfield');
    }

    /** Creates the field with fields.create and adds it to the list the dialog was opened for. */
    public function createSubfield(): void
    {
        $this->authorizeEditing();
        $list = Value::string($this->newSubfield['list']);
        $name = trim(Value::string($this->newSubfield['name']));
        $type = Value::string($this->newSubfield['type']);

        if (! in_array($type, array_keys($this->subfieldTypes()), true)) {
            throw ValidationException::withMessages(['newSubfield.type' => __('lynx-admin::fields.subfields.item_type')]);
        }

        $translatable = $this->isMultilingual() ? (bool) $this->newSubfield['translatable'] : $this->typeCanTranslate($type);

        try {
            lynx()->fields->add($name, $type, $translatable, LocalizedForm::fromForm($this->newSubfield['label']));
        } catch (ValidationException $exception) {
            throw $this->inDialog($exception);
        }

        $this->addSubfield($list, $name);
        $this->newSubfield['name'] = '';
        $this->dispatch('close-dialog-new-subfield');
    }

    /** Whether the field form shows the builder: repeaters and matrices. */
    protected function hasItemBuilder(): bool
    {
        return in_array($this->type, [RepeaterType::name(), MatrixType::name()], true);
    }

    /** The setting the builder edits, left out of the generic settings form. */
    protected function itemSetting(): ?string
    {
        return match ($this->type) {
            MatrixType::name() => 'types',
            RepeaterType::name() => 'fields',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function loadItemSettings(array $config): void
    {
        $this->itemTypes = ItemSettingsForm::typesToForm($config['types'] ?? []);
        $this->itemFields = ItemSettingsForm::subfieldsToForm($config['fields'] ?? []);
        $this->savedSlugs = array_column($this->itemTypes, 'slug', 'key');
    }

    protected function resetItemSettings(): void
    {
        $this->itemTypes = [];
        $this->itemFields = [];
    }

    /**
     * The builder's settings for fields.create or fields.update; saved slugs win over edited ones.
     *
     * @return array<string, mixed>
     */
    protected function itemSettings(): array
    {
        return match ($this->itemSetting()) {
            'types' => ['types' => array_map($this->keepSavedSlug(...), ItemSettingsForm::typesFromForm($this->itemTypes), array_keys($this->itemTypes))],
            'fields' => ['fields' => ItemSettingsForm::subfieldsFromForm($this->itemFields)],
            default => [],
        };
    }

    /**
     * Fields a list may offer, by name: the site's own, not the ones modules keep for themselves.
     *
     * @return array<string, array{label: string, type: string, translatable: bool}>
     */
    protected function subfieldChoices(): array
    {
        return lynx()->fields->all()
            ->reject(fn (Field $field): bool => app(SchemaOwners::class)->isSystemField((string) $field->name))
            ->filter(fn (Field $field): bool => ItemSettingsRules::canBeSubfield($field->fieldType()))
            ->sortBy('name')
            ->mapWithKeys(fn (Field $field): array => [(string) $field->name => ['label' => (string) $field->label, 'type' => (string) $field->type, 'translatable' => $field->isTranslatable()]])
            ->all();
    }

    /**
     * Types a field created on the spot may have.
     *
     * @return array<string, mixed>
     */
    protected function subfieldTypes(): array
    {
        $types = app(FieldTypeChoices::class)->for(lynx()->fields->types());

        return array_filter($types, fn (string $name): bool => ItemSettingsRules::canBeSubfield(lynx()->fieldTypes()->get($name)), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Pages using each saved block type, counting unpublished and trashed ones, so the builder can
     * warn before a type in use is removed: their blocks would no longer save.
     *
     * @return array<string, int>
     */
    protected function typeUsage(): array
    {
        if ($this->field === null || $this->type !== MatrixType::name()) {
            return [];
        }

        return collect(array_values($this->savedSlugs))
            ->mapWithKeys(fn (string $slug): array => [$slug => lynx()->pages->count("{$this->field}.type={$slug}, include=all")])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $type
     * @return array<string, mixed>
     */
    private function keepSavedSlug(array $type, int $index): array
    {
        $saved = $this->savedSlugs[$this->itemTypes[$index]['key']] ?? null;

        return $saved === null ? $type : ['slug' => $saved] + $type;
    }

    /** fields.create names its inputs "name", "type"…; the dialog's are "newSubfield.name"… */
    private function inDialog(ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $errors) {
            $messages["newSubfield.{$key}"] = $errors;
        }

        return ValidationException::withMessages($messages);
    }

    /**
     * @return list<array<string, mixed>>|null null when there is no such list
     */
    private function subfieldList(string $list): ?array
    {
        if ($list === 'fields') {
            return $this->itemFields;
        }

        if (preg_match('/^types\.(\d+)$/', $list, $match) === 1 && isset($this->itemTypes[(int) $match[1]])) {
            return $this->itemTypes[(int) $match[1]]['fields'];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function setSubfieldList(string $list, array $fields): void
    {
        if ($list === 'fields') {
            $this->itemFields = $fields;
        } elseif (preg_match('/^types\.(\d+)$/', $list, $match) === 1 && isset($this->itemTypes[(int) $match[1]])) {
            $this->itemTypes[(int) $match[1]]['fields'] = $fields;
        }
    }
}
