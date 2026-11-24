{{-- A section of the page editor: collapsible, with its field count and a dot when a field falls back to another language. --}}
@php
    $section = $block->section;
    $names = array_values(array_filter($block->fields, $isShown));
    $hasError = array_intersect($names, $errorFields) !== [];
    $fallsBack = collect($names)->contains(fn ($name) => collect($views[$name]->values)->contains(fn ($state) => $state->fallsBackTo !== null));
@endphp
@if ($names !== [])
    <x-lynx-admin::section :id="'section-'.$section->key" :title="$section->label->forTemplate((string) $page->template()->name)"
        :remember="$page->template()->name.':'.$section->key" :collapsed="$section->collapsed" :has-error="$hasError"
        body-class="flex flex-wrap overflow-hidden" data-section="{{ $section->key }}" wire:key="section-{{ $section->key }}">
        <x-slot:meta>
            <span class="text-muted-foreground text-sm">{{ trans_choice('lynx-admin::editor.fields_count', count($names)) }}</span>
            @if ($fallsBack)
                <span class="lynx-tab-state is-fallback" title="{{ __('lynx-admin::editor.falls_back_here') }}"></span>
            @endif
        </x-slot:meta>
        @foreach ($names as $name)
            @include('lynx-admin::livewire.partials.editor-field', ['field' => $views[$name]])
        @endforeach
    </x-lynx-admin::section>
@endif
