@if (($field->settings['format'] ?? 'plain') === 'html')
    {{-- Rich text: Tiptap (resources/js/admin.js). wire:ignore keeps Livewire's morphing away from the editor. --}}
    <div
        class="lynx-richtext"
        wire:ignore
        x-data="lynxRichText($wire.$entangle('{{ $model }}', {{ ($live ?? false) ? 'true' : 'false' }}), {{ $disabled ? 'true' : 'false' }}, @js(trim(strip_tags($placeholder))), 'field-{{ $field->name }}', @js(['pageId' => $field->pageId, 'language' => $code]))"
    >
        @unless ($disabled)
            <div class="lynx-richtext-toolbar" role="toolbar" aria-label="Formatting for {{ $field->label }}">
                @foreach (['bold' => ['B', 'Bold'], 'italic' => ['I', 'Italic'], 'strike' => ['S', 'Strikethrough'], 'h2' => ['H2', 'Heading'], 'h3' => ['H3', 'Subheading'], 'bulletList' => ['•', 'Bullet list'], 'orderedList' => ['1.', 'Numbered list'], 'blockquote' => ['❝', 'Quote']] as $tool => [$text, $label])
                    <button type="button" @click="run('{{ $tool }}')" :aria-pressed="active.{{ $tool }} ? 'true' : 'false'" aria-label="{{ $label }}" title="{{ $label }}">{{ $text }}</button>
                @endforeach
                <button type="button" @click="link()" :aria-pressed="active.link ? 'true' : 'false'" aria-label="Link" title="Link">Link</button>
                <span class="lynx-richtext-separator" aria-hidden="true"></span>
                <button type="button" @click="undo()" aria-label="Undo" title="Undo">↶</button>
                <button type="button" @click="redo()" aria-label="Redo" title="Redo">↷</button>
            </div>
        @endunless
        <div x-ref="editor"></div>
    </div>
@else
<textarea
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-textarea"
    rows="6"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
></textarea>
@endif
