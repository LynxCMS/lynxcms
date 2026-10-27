{{-- A choice field: a select (default), radios, or checkboxes (default with "multiple"); labels in the editor's language. --}}
@php
    $isMultiple = ($field->settings['multiple'] ?? false) === true;
    $style = (string) ($field->settings['input'] ?? ($isMultiple ? 'checkboxes' : 'select'));
    // Checkboxes pick several and radios one, whatever the setting says.
    $style = match (true) { $style === 'select' => 'select', $isMultiple => 'checkboxes', default => 'radios' };
    $options = array_map(fn ($option) => ['value' => (string) ($option['value'] ?? ''), 'label' => \LynxCms\Core\Support\LocalizedText::from($option['label'] ?? '')->in()], (array) ($field->settings['options'] ?? []));
    $wire = ($live ?? false) ? 'wire:model.live' : 'wire:model';
@endphp
@if ($style === 'select')
    <select id="input-{{ $field->name }}-{{ $code }}" class="blat-select max-w-sm" {{ $wire }}="{{ $model }}" @if ($isMultiple) multiple @endif
            aria-labelledby="field-{{ $field->name }}" @disabled($disabled) @error('values.'.$field->name) aria-invalid="true" @enderror>
        {{-- A required choice with a default always has a value, so it offers no empty option (ADR-0088). --}}
        @unless ($isMultiple || ($field->isRequired && ($field->settings['default'] ?? null) !== null)) <option value="">—</option> @endunless
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
@else
    <div class="flex flex-wrap gap-x-5 gap-y-2" role="{{ $isMultiple ? 'group' : 'radiogroup' }}" aria-labelledby="field-{{ $field->name }}">
        @foreach ($options as $option)
            <label class="flex items-center gap-2 text-sm">
                <input type="{{ $isMultiple ? 'checkbox' : 'radio' }}" class="{{ $isMultiple ? 'blat-checkbox' : 'blat-radio' }}"
                       name="{{ $model }}" value="{{ $option['value'] }}" {{ $wire }}="{{ $model }}" @disabled($disabled)>
                {{ $option['label'] }}
            </label>
        @endforeach
    </div>
@endif
