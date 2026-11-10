{{-- A text field with a "max_length" counts its characters; email and URL fields reuse this input without one. --}}
@php($maxLength = isset($inputType) ? 0 : (int) ($field->settings['max_length'] ?? 0))
@if ($maxLength > 0)
<div class="grid gap-1" x-data="{ count: 0 }" x-init="$nextTick(() => count = [...$refs.box.value].length)">
@endif
<input
    type="{{ $inputType ?? 'text' }}"
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @if ($maxLength > 0) x-ref="box" x-on:input="count = [...$event.target.value].length" @endif
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
@if ($maxLength > 0)
    @include('lynx-admin::inputs.partials.char-counter')
</div>
@endif
