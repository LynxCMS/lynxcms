<select id="{{ $id }}" class="lynx-form-input" wire:model.live="{{ $path }}" @if ($field->isRequired) required @endif aria-invalid="{{ $errors->has($path) ? 'true' : 'false' }}">
    <option value="">{{ __('lynx-forms::forms.choose') }}</option>
    @foreach ($field->options as $value => $option)
        <option value="{{ $value }}">{{ $option->in() }}</option>
    @endforeach
</select>
