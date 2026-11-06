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
                    <button type="button" @click="run('{{ $tool }}')" :aria-pressed="active.{{ $tool }} ? 'true' : 'false'" aria-label="{{ $label }}" title="{{ $label }}" data-richtext-tool="{{ $tool }}">{{ $text }}</button>
                @endforeach
                <button type="button" @click="link()" :aria-pressed="active.link ? 'true' : 'false'" aria-label="{{ __('lynx-admin::inputs.rich_text.link') }}" title="{{ __('lynx-admin::inputs.rich_text.link') }}">{{ __('lynx-admin::inputs.rich_text.link') }}</button>
                {{-- The extra tools the field turns on (its "tools" setting). --}}
                @if ($extraButtons !== [])<span class="lynx-richtext-separator" aria-hidden="true"></span>@endif
                @foreach ($extraButtons as $button)
                    @php($label = __($button['label']))
                    <button type="button" @click="run('{{ $button['tool'] }}')" :aria-pressed="active.{{ $button['tool'] }} ? 'true' : 'false'" aria-label="{{ $label }}" title="{{ $label }}" data-richtext-tool="{{ $button['tool'] }}"><x-dynamic-component :component="$button['icon']" class="size-4" aria-hidden="true" /></button>
                @endforeach
                <span class="lynx-richtext-separator" aria-hidden="true"></span>
                <button type="button" @click="undo()" aria-label="{{ __('lynx-admin::inputs.rich_text.undo') }}" title="{{ __('lynx-admin::inputs.rich_text.undo') }}">↶</button>
                <button type="button" @click="redo()" aria-label="{{ __('lynx-admin::inputs.rich_text.redo') }}" title="{{ __('lynx-admin::inputs.rich_text.redo') }}">↷</button>
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
