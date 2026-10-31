{{-- A section of the page editor: collapsible, with its field count and a dot when a field falls back to another language. --}}
@php
    $section = $block->section;
    $names = array_values(array_filter($block->fields, $isShown));
    $hasError = array_intersect($names, $errorFields) !== [];
    $fallsBack = collect($names)->contains(fn ($name) => collect($views[$name]->values)->contains(fn ($state) => $state->fallsBackTo !== null));
@endphp
@if ($names !== [])
    <section class="@container bg-card text-card-foreground min-w-0 rounded-xl border shadow-sm" data-section="{{ $section->key }}" data-collapsed="{{ $section->collapsed ? 'true' : 'false' }}"
             wire:key="section-{{ $section->key }}" x-data="lynxEditorSection(@js($page->template()->name.':'.$section->key), @js($section->collapsed), @js($hasError))">
        <button type="button" class="flex w-full items-center gap-2 px-5 py-3 text-start" x-on:click="toggle()" x-bind:aria-expanded="open" aria-controls="section-{{ $section->key }}">
            <x-lucide-chevron-right class="text-muted-foreground size-4 shrink-0 transition-transform" x-bind:class="open && 'rotate-90'" aria-hidden="true" />
            <span class="font-semibold">{{ $section->label->in() }}</span>
            <span class="text-muted-foreground text-sm">{{ trans_choice('lynx-admin::editor.fields_count', count($names)) }}</span>
            @if ($fallsBack)
                <span class="lynx-tab-state is-fallback" title="{{ __('lynx-admin::editor.falls_back_here') }}"></span>
            @endif
        </button>
        <div id="section-{{ $section->key }}" class="flex flex-wrap overflow-hidden rounded-b-xl border-t" x-show="open" @if ($section->collapsed && ! $hasError) x-cloak @endif>
            @foreach ($names as $name)
                @include('lynx-admin::livewire.partials.editor-field', ['field' => $views[$name]])
            @endforeach
        </div>
    </section>
@endif
