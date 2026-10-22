{{-- One field, by type. $path is its wire:model path: data.{name}, or data.{group}.{row}.{name}. --}}
@php
    $id = 'f-'.str_replace('.', '-', $path);
    $label = $field->label->in();
    $isFile = $field->type === 'file';
    $errorKey = $path;
@endphp
<div @class(['lynx-form-field', 'is-'.$field->type, 'w-'.$field->width]) wire:key="{{ $id }}">
    @switch($field->type)
        @case('checkbox')
            <label class="lynx-form-check">
                <input type="checkbox" id="{{ $id }}" wire:model.live="{{ $path }}" @if ($field->isRequired) required @endif aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}">
                <span>{{ $label }}</span>
            </label>
            @break
        @case('group')
            @include('lynx-forms::fields.group')
            @break
        @default
            <label class="lynx-form-label" for="{{ $id }}">{{ $label }}@if ($field->isRequired)<span class="lynx-form-required" aria-hidden="true"> *</span>@endif</label>
            @if ($field->help)
                <p class="lynx-form-help" id="{{ $id }}-help">{{ $field->help->in() }}</p>
            @endif
            @include('lynx-forms::fields.'.match ($field->type) {
                'textarea' => 'textarea',
                'select' => 'select',
                'radio', 'checkboxes' => 'choices',
                'file' => 'file',
                default => 'input',
            })
    @endswitch
    @error($errorKey) <p class="lynx-form-error" role="alert">{{ $message }}</p> @enderror
    @if ($isFile)
        @error($errorKey.'.*') <p class="lynx-form-error" role="alert">{{ $message }}</p> @enderror
    @endif
</div>
