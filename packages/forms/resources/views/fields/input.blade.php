<input
    id="{{ $id }}"
    class="lynx-form-input"
    type="{{ ['email' => 'email', 'tel' => 'tel', 'number' => 'number', 'date' => 'date'][$field->type] ?? 'text' }}"
    wire:model.live.blur="{{ $path }}"
    @if ($field->type === 'email') autocomplete="email" @elseif ($field->type === 'tel') autocomplete="tel" @endif
    @if ($field->max && $field->type !== 'number') maxlength="{{ $field->max }}" @endif
    @if ($field->isRequired) required @endif
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    aria-invalid="{{ $errors->has($path) ? 'true' : 'false' }}"
>
