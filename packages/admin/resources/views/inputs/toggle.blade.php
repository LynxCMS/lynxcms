{{-- A toggle: one checkbox labeled Yes (default), or a switch with "input": "switch". Both hold true or false. --}}
@if (($field->settings['input'] ?? 'checkbox') === 'switch')
    <span class="inline-flex" data-toggle-input="switch">
        <x-lynx-admin::ui.switch wire:model="{{ $model }}" aria-labelledby="field-{{ $field->name }}" :disabled="$disabled" />
    </span>
@else
    <label class="inline-flex items-center gap-2 text-sm" data-toggle-input="checkbox">
        <input class="blat-checkbox" type="checkbox" wire:model="{{ $model }}" aria-labelledby="field-{{ $field->name }}" @disabled($disabled)>
        {{ __('lynx-admin::inputs.yes') }}
    </label>
@endif
