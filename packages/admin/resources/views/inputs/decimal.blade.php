{{-- A decimal number: a text input with the decimal keypad, so "3,5" works as well as "3.5"; the field rounds it to its places. --}}
<input
    type="text"
    inputmode="decimal"
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input max-w-48"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
