{{-- A native time picker; it offers seconds for fields that keep them. --}}
<input
    type="time"
    @if (($field->settings['seconds'] ?? false) === true) step="1" @endif
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input max-w-40"
    @if ($live ?? false) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
