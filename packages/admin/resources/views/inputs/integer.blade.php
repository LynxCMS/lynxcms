<input
    type="number"
    step="1"
    inputmode="numeric"
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input max-w-48"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
