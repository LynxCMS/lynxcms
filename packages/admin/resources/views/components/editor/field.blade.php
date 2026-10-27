@props(['field', 'languages', 'input' => null, 'disabled' => false, 'live' => false, 'current' => [], 'required' => null, 'showType' => false, 'stacked' => false, 'cell' => false])
{{-- showType: the field type under the label, for superusers only.
     cell: a cell of a page editor card, which wraps fields side by side by their width (ADR-0063). --}}
@php
    $codes = array_keys($field->values);
    $hasError = collect($errors->keys())->contains(fn (string $key): bool => $key === 'values.'.$field->name || str_starts_with($key, 'values.'.$field->name.'.'));
    $isCollapsible = $field->display->isCollapsible();
    $startsClosed = $isCollapsible && $field->startsCollapsed() && ! $hasError;
    $isNarrow = $cell && $field->width < \LynxCms\Core\Templates\EditorSettings::FULL_WIDTH;
    $widthClass = [25 => '@xl:basis-1/4', 33 => '@xl:basis-1/3', 50 => '@xl:basis-1/2', 66 => '@xl:basis-2/3', 75 => '@xl:basis-3/4'][$field->width] ?? '';
@endphp
{{-- BlatUI tabs hold the language shown (tab); double-clicking a tab switches every field. The label sits beside the input when the card (an @container) is wide enough, unless stacked or narrower than the card: above it. --}}
<div data-field="{{ $field->name }}" data-editor="{{ $field->display->value }}" data-collapsed="{{ $startsClosed ? 'true' : 'false' }}" data-width="{{ $field->width }}"
     @class(['min-w-0', 'shrink-0 grow-0 basis-full border-b border-border -mb-px' => $cell, $widthClass => $cell, 'border-e -me-px' => $isNarrow])
     x-data="{ open: @js(! $startsClosed) }">
<x-lynx-admin::ui.tabs :value="$codes[0]" class="{{ $stacked || $isNarrow ? 'grid gap-2 p-5' : 'grid gap-2 p-5 @xl:grid-cols-[12rem_minmax(0,1fr)] @xl:gap-6' }}"
     x-on:lynx-editor-language.window="if ({{ Js::from($codes) }}.includes($event.detail)) tab = $event.detail">
    <div>
        @if ($isCollapsible)
            <button type="button" id="field-{{ $field->name }}" class="flex items-center gap-1.5 font-medium text-start" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="field-{{ $field->name }}-input">
                <x-lynx-admin::ui.icon name="chevron-right" class="text-muted-foreground size-4 shrink-0 transition-transform" x-bind:class="open && 'rotate-90'" aria-hidden="true" />
                @if ($field->icon)<x-lynx-admin::ui.icon :name="$field->icon" data-icon="{{ $field->icon }}" class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />@endif
                <span>{{ $field->label }}@if ($required ?? $field->isRequired)<span class="text-destructive" aria-label="required"> *</span>@endif</span>
            </button>
        @else
            <p id="field-{{ $field->name }}" class="flex items-center gap-1.5 font-medium">
                @if ($field->icon)<x-lynx-admin::ui.icon :name="$field->icon" data-icon="{{ $field->icon }}" class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />@endif
                <span>{{ $field->label }}@if ($required ?? $field->isRequired)<span class="text-destructive" aria-label="required"> *</span>@endif</span>
            </p>
        @endif
        @if ($field->display === \LynxCms\Core\Templates\FieldDisplay::Locked)
            <p class="text-muted-foreground text-xs">Set by the site, not editable here.</p>
        @endif
        @if ($showType)
            <p class="text-muted-foreground text-sm">{{ $field->type }}{{ $field->isTranslatable && count($languages) > 1 ? ', translatable' : '' }}</p>
        @endif
        @if ($field->description)
            <p class="text-muted-foreground text-sm mt-1">{{ $field->description }}</p>
        @endif
    </div>

    <div class="min-w-0" id="field-{{ $field->name }}-input" aria-labelledby="field-{{ $field->name }}" @if ($isCollapsible) x-show="open" @if ($startsClosed) x-cloak @endif @endif>
        @if ($field->isTranslatable && count($languages) > 1)
            <x-lynx-admin::ui.tabs-list variant="compact" class="mb-2">
                @foreach ($languages as $language)
                    @php($state = $field->values[$language['code']])
                    <x-lynx-admin::ui.tabs-trigger :value="$language['code']"
                            x-on:dblclick="$dispatch('lynx-editor-language', '{{ $language['code'] }}')"
                            :title="$state->isOwn ? 'Filled' : ($state->fallsBackTo ? 'Falls back to '.$state->fallsBackTo : 'Empty')">
                        {{ $language['label'] }}
                        <span aria-hidden="true" @class(['lynx-tab-state', 'is-filled' => $state->isOwn, 'is-fallback' => $state->fallsBackTo !== null])></span>
                    </x-lynx-admin::ui.tabs-trigger>
                @endforeach
            </x-lynx-admin::ui.tabs-list>
        @endif

        @foreach ($field->values as $code => $state)
            <div x-show="tab === '{{ $code }}'" @if (! $loop->first) x-cloak @endif>
                @if ($input)
                    {{-- The fallback value shows as the placeholder while this language is empty, else the template's placeholder. --}}
                    @include($input, [
                        'field' => $field,
                        'code' => $code,
                        'model' => 'values.'.$field->name.'.'.$code,
                        'placeholder' => $state->fallsBackTo !== null && is_scalar($state->value) ? (string) $state->value : ($field->placeholder ?? ''),
                        'disabled' => $disabled,
                        'live' => $live,
                        'value' => $current[$code] ?? null,
                        'readOnly' => $state->value,
                    ])
                    @if ($state->fallsBackTo !== null)
                        {{-- A translation hint: the tab's state marker says it already, so it shows only when the editor turns hints on. --}}
                        <p class="lynx-hint text-muted-foreground text-sm mt-1">Empty in this language: visitors see the {{ $state->fallsBackTo }} value.</p>
                    @endif
                    @if (($field->settings['formatters'] ?? []) !== [] && trim((string) ($current[$code] ?? '')) !== '')
                        {{-- What visitors see: the field's format and formatters (ADR-0031), cleaned by core. --}}
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-muted-foreground">Preview</summary>
                            <div class="lynx-preview mt-2">{!! lynx()->formatters()->render(lynx()->fields->get($field->name), $current[$code], $code) !!}</div>
                        </details>
                    @endif
                @elseif ($state->isOwn)
                    <x-lynx-admin::editor.value :type="$field->type" :value="$state->value" />
                @elseif ($state->fallsBackTo)
                    <p class="lynx-hint text-muted-foreground text-sm mb-1">Falls back to {{ $state->fallsBackTo }}:</p>
                    <div class="lynx-fallback"><x-lynx-admin::editor.value :type="$field->type" :value="$state->value" /></div>
                @else
                    <p class="text-muted-foreground text-sm">Empty</p>
                @endif
            </div>
        @endforeach

        @if ($field->notes)
            <p class="text-muted-foreground text-xs mt-1">{{ $field->notes }}</p>
        @endif

        @error('values.'.$field->name) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    </div>
</x-lynx-admin::ui.tabs>
</div>
