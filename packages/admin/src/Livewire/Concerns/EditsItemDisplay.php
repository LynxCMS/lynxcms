<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Admin\Editor\LocalizedForm;

/**
 * A repeater's or matrix's limits and display, on the field form's Limits and display tab: how
 * many items, how deep, the add button's text in every language, how items start and their
 * header. The add button's text is per language, which the generic settings form can't edit,
 * so it has a property of its own; the others stay in $settings.
 *
 * @internal
 */
trait EditsItemDisplay
{
    /** Settings the Limits and display tab edits, left out of the generic settings form. */
    public const array ITEM_DISPLAY_SETTINGS = ['min', 'max', 'max_depth', 'item_label', 'add_label', 'collapse'];

    /** @var array<string, string> language code => the add button's text */
    public array $addLabel = [];

    /**
     * @param  array<string, mixed>  $config
     */
    protected function loadItemDisplay(array $config): void
    {
        $this->addLabel = LocalizedForm::toForm($config['add_label'] ?? null);

        // The default, written out: the first choice on the tab stands for both.
        if (($this->settings['collapse'] ?? '') === 'new_open') {
            $this->settings['collapse'] = '';
        }
    }

    /**
     * For fields.create or fields.update; null removes the text, so the button says "Add".
     *
     * @return array<string, mixed>
     */
    protected function itemDisplaySettings(): array
    {
        return $this->hasItemBuilder() ? ['add_label' => LocalizedForm::fromForm($this->addLabel)] : [];
    }
}
