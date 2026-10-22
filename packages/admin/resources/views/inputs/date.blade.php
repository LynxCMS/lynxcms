{{-- A native date picker; a date and time one for fields with "time". --}}
<input
    type="{{ ($field->settings['time'] ?? false) === true ? 'datetime-local' : 'date' }}"
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-input max-w-56"
    @if ($live ?? false) wire:model.live="{{ $model }}" @else wire:model="{{ $model }}" @endif
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
>
