<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Admin\Editor\ChoicesForm;
use LynxCms\Core\Fields\Types\ChoiceType;
use LynxCms\Core\Support\Value;

/**
 * The field form's choices editor for choice fields: the options, a label per language each, and
 * which of them new pages and items start with (ADR-0088).
 *
 * @internal
 */
trait EditsChoiceOptions
{
    /**
     * Loads "options" and marks the rows of "default".
     *
     * @param  array<string, mixed>  $config
     */
    protected function loadChoiceOptions(array $config): void
    {
        if ($this->hasChoices()) {
            $this->settings['options'] = ChoicesForm::withDefaults(ChoicesForm::toForm($config['options'] ?? []), $config['default'] ?? null);
        }
    }

    /**
     * "options" and "default" for fields.create or fields.update; a null default removes it.
     *
     * @return array<string, mixed>
     */
    protected function choiceSettings(): array
    {
        if (! $this->hasChoices()) {
            return [];
        }

        $rows = $this->settings['options'] ?? [];
        $defaults = ChoicesForm::defaultsFromForm($rows);
        $isMultiple = Value::bool($this->settings['multiple'] ?? false);

        return [
            'options' => ChoicesForm::fromForm($rows),
            'default' => match (true) {
                $defaults === [] => null,
                $isMultiple => $defaults,
                default => $defaults[0],
            },
        ];
    }

    /**
     * Settings the choices editor shows, left out of the generic settings form.
     *
     * @return list<string>
     */
    protected function choiceSettingsEditedHere(): array
    {
        return $this->hasChoices() ? ['default'] : [];
    }

    /** Whether the type keeps a list of choices in "options", edited with the choices editor. */
    protected function hasChoices(): bool
    {
        return $this->type === ChoiceType::name();
    }
}
