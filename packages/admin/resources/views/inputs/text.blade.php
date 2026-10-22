<input
    type="{{ $inputType ?? 'text' }}"
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
