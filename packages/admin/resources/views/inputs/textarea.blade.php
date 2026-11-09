@if (($field->settings['format'] ?? 'plain') === 'html')
    @php($extraButtons = app(\LynxCms\Admin\Editor\RichTextTools::class)->buttons(\LynxCms\Core\Support\Value::strings($field->settings['tools'] ?? [])))
    {{-- Rich text: Tiptap (resources/js/admin.js). wire:ignore keeps Livewire's morphing away from the editor. --}}
    <div
        class="lynx-richtext"
        wire:ignore
        x-data="lynxRichText($wire.$entangle('{{ $model }}', {{ ($live ?? false) ? 'true' : 'false' }}), {{ $disabled ? 'true' : 'false' }}, @js(trim(strip_tags($placeholder))), 'field-{{ $field->name }}', @js(['pageId' => $field->pageId, 'language' => $code]))"
    >
        @unless ($disabled)
            <div class="lynx-richtext-toolbar" role="toolbar" aria-label="{{ __('lynx-admin::inputs.rich_text.toolbar', ['field' => $field->label]) }}">
                @foreach (['bold' => ['B', 'bold'], 'italic' => ['I', 'italic'], 'strike' => ['S', 'strike'], 'h2' => ['H2', 'heading'], 'h3' => ['H3', 'subheading'], 'bulletList' => ['•', 'bullet_list'], 'orderedList' => ['1.', 'numbered_list'], 'blockquote' => ['❝', 'quote']] as $tool => [$text, $labelKey])
                    @php($label = __('lynx-admin::inputs.rich_text.'.$labelKey))
                    <button type="button" :disabled="isSource" @click="run('{{ $tool }}')" :aria-pressed="active.{{ $tool }} ? 'true' : 'false'" aria-label="{{ $label }}" title="{{ $label }}" data-richtext-tool="{{ $tool }}">{{ $text }}</button>
                @endforeach
                <button type="button" @click="link()" :disabled="isSource" :aria-pressed="active.link ? 'true' : 'false'" aria-label="{{ __('lynx-admin::inputs.rich_text.link') }}" title="{{ __('lynx-admin::inputs.rich_text.link') }}">{{ __('lynx-admin::inputs.rich_text.link') }}</button>
                {{-- The extra tools the field turns on (its "tools" setting). --}}
                @if ($extraButtons !== [])<span class="lynx-richtext-separator" aria-hidden="true"></span>@endif
                @foreach ($extraButtons as $button)
                    @php($label = __($button['label']))
                    <button type="button" :disabled="isSource" @click="run('{{ $button['tool'] }}')" :aria-pressed="active.{{ $button['tool'] }} ? 'true' : 'false'" aria-label="{{ $label }}" title="{{ $label }}" data-richtext-tool="{{ $button['tool'] }}"><x-dynamic-component :component="$button['icon']" class="size-4" aria-hidden="true" /></button>
                @endforeach
                <span class="lynx-richtext-separator" aria-hidden="true"></span>
                <button type="button" @click="undo()" aria-label="{{ __('lynx-admin::inputs.rich_text.undo') }}" title="{{ __('lynx-admin::inputs.rich_text.undo') }}">↶</button>
                <button type="button" @click="redo()" aria-label="{{ __('lynx-admin::inputs.rich_text.redo') }}" title="{{ __('lynx-admin::inputs.rich_text.redo') }}">↷</button>
                <span class="lynx-richtext-separator" aria-hidden="true"></span>
                {{-- The field's HTML, to read or edit as markup; the server cleans it on save like any rich text. --}}
                <button type="button" data-richtext-source-toggle @click="toggleSource()" :aria-pressed="isSource ? 'true' : 'false'"
                        aria-label="{{ __('lynx-admin::inputs.rich_text.html') }}" title="{{ __('lynx-admin::inputs.rich_text.html_help') }}">{{ __('lynx-admin::inputs.rich_text.html') }}</button>
            </div>
        @endunless
        <div x-ref="editor" x-show="! isSource"></div>
        @unless ($disabled)
            <textarea class="blat-textarea lynx-richtext-source font-mono text-sm" rows="10" x-show="isSource" x-cloak data-richtext-source
                      x-model="source" @input="sourceChanged()" spellcheck="false" aria-label="{{ __('lynx-admin::inputs.rich_text.html_field', ['field' => $field->label]) }}"></textarea>
        @endunless
    </div>
@else
@php($maxLength = (int) ($field->settings['max_length'] ?? 0))
@if ($maxLength > 0)
<div class="grid gap-1" x-data="{ count: 0 }" x-init="$nextTick(() => count = [...$refs.box.value].length)">
@endif
<textarea
    id="input-{{ $field->name }}-{{ $code }}"
    class="blat-textarea"
    rows="6"
    @if ($live ?? false) wire:model.live.debounce.400ms="{{ $model }}" @else wire:model="{{ $model }}" @endif
    placeholder="{{ $placeholder }}"
    aria-labelledby="field-{{ $field->name }}"
    @if ($maxLength > 0) x-ref="box" x-on:input="count = [...$event.target.value].length" @endif
    @disabled($disabled)
    @error('values.'.$field->name) aria-invalid="true" @enderror
></textarea>
@if ($maxLength > 0)
    @include('lynx-admin::inputs.partials.char-counter')
</div>
@endif
@endif
