<div class="lynx-form-choices" role="{{ $field->type === 'radio' ? 'radiogroup' : 'group' }}" aria-labelledby="{{ $id }}">
    @foreach ($field->options as $value => $option)
        <label class="lynx-form-check">
            <input type="{{ $field->type === 'radio' ? 'radio' : 'checkbox' }}" value="{{ $value }}" wire:model.live="{{ $path }}">
            <span>{{ $option->in() }}</span>
        </label>
    @endforeach
</div>
