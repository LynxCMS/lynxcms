{{-- Rows of a group field, sortable by dragging the handle. --}}
<fieldset class="lynx-form-group">
    <legend class="lynx-form-label">{{ $field->label->in() }}</legend>
    <div class="lynx-form-rows" wire:sort="sortRow">
        @foreach ((array) ($data[$field->name] ?? []) as $index => $row)
            <div class="lynx-form-row" wire:key="row-{{ $field->name }}-{{ $row['_key'] ?? $index }}" wire:sort:item="{{ $field->name }}:{{ $row['_key'] ?? $index }}">
                <span class="lynx-form-handle" wire:sort:handle aria-hidden="true">⋮⋮</span>
                <div class="lynx-form-fields">
                    @foreach ($field->fields as $subfield)
                        @include('lynx-forms::fields.field', ['field' => $subfield, 'path' => 'data.'.$field->name.'.'.$index.'.'.$subfield->name])
                    @endforeach
                </div>
                <button type="button" class="lynx-form-button is-link" wire:click="removeRow('{{ $field->name }}', {{ $index }})">{{ __('lynx-forms::forms.remove') }}</button>
            </div>
        @endforeach
    </div>
    @if ($this->canAddRow($field))
        <button type="button" class="lynx-form-button is-secondary" wire:click="addRow('{{ $field->name }}')">+ {{ __('lynx-forms::forms.add_row') }}</button>
    @endif
</fieldset>
