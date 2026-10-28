{{-- A page reference: the title search (default), or a select, radios or checkboxes listing the allowed pages. --}}
@php
    $settings = (array) $field->settings;
    $style = \LynxCms\Admin\Editor\PageChoices::style($settings);
@endphp
@if ($disabled)
    <x-lynx-admin::editor.value type="page" :value="$readOnly ?? []" />
@elseif ($style === 'search')
    <livewire:lynx-admin.page-picker
        wire:model="{{ $model }}"
        :value="$value ?? []"
        :multiple="\LynxCms\Admin\Editor\PageChoices::isMultiple($settings)"
        :templates="(array) ($settings['templates'] ?? [])"
        :max="\LynxCms\Core\Support\Value::int($settings['max'] ?? 0)"
        :key="'picker-'.$model"
    />
@else
    @php
        $isMultiple = ! \LynxCms\Admin\Editor\PageChoices::holdsOne($settings);
        $options = app(\LynxCms\Admin\Editor\PageChoices::class)->options($settings, \LynxCms\Core\Support\Value::ints((array) ($value ?? [])));
        $wire = ($live ?? false) ? 'wire:model.live' : 'wire:model';
    @endphp
    @if ($style === 'select')
        <select id="input-{{ $field->name }}-{{ $code }}" class="blat-select max-w-sm" {{ $wire }}="{{ $model }}" @if ($isMultiple) multiple @endif
                aria-labelledby="field-{{ $field->name }}" @error('values.'.$field->name) aria-invalid="true" @enderror>
            @unless ($isMultiple) <option value="">—</option> @endunless
            @foreach ($options as $option)
                <option value="{{ $option['id'] }}">{{ $option['title'] }}</option>
            @endforeach
        </select>
    @else
        <div class="flex flex-wrap gap-x-5 gap-y-2" role="{{ $isMultiple ? 'group' : 'radiogroup' }}" aria-labelledby="field-{{ $field->name }}">
            @foreach ($options as $option)
                <label class="flex items-center gap-2 text-sm">
                    <input type="{{ $isMultiple ? 'checkbox' : 'radio' }}" class="{{ $isMultiple ? 'blat-checkbox' : 'blat-radio' }}"
                           name="{{ $model }}" value="{{ $option['id'] }}" {{ $wire }}="{{ $model }}">
                    {{ $option['title'] }}
                </label>
            @endforeach
        </div>
    @endif
@endif
