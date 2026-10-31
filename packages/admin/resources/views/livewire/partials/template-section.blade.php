{{-- A section of the layout builder: drag it with its fields; its fields form their own list. --}}
@php($sectionLabel = collect($sections[$key]['label'] ?? [])->filter()->first() ?? $key)
<li wire:key="layout-section-{{ $key }}" wire:sort:item="section:{{ $key }}" x-data="{ open: false }" class="bg-muted/40 m-2 rounded-lg border" data-layout-section="{{ $key }}">
    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
        @if ($canEdit) <span wire:sort:handle class="cursor-grab select-none text-muted-foreground active:cursor-grabbing" title="{{ __('lynx-admin::templates.section.drag') }}" aria-hidden="true">⠿</span> @endif
        <span class="font-semibold">{{ $sectionLabel }}</span>
        <span class="text-muted-foreground text-sm">{{ trans_choice('lynx-admin::templates.section.fields', count($sectionFields[$key] ?? [])) }}</span>
        @if ($sections[$key]['collapsed'] ?? false) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::templates.section.starts_collapsed') }}</x-lynx-admin::ui.badge> @endif
        <span class="ms-auto flex items-center gap-1 text-sm">
            @if ($canEdit)
                <label class="sr-only" for="place-section-{{ $key }}">{{ __('lynx-admin::templates.section.place', ['section' => $sectionLabel]) }}</label>
                <select id="place-section-{{ $key }}" class="blat-select h-8 w-32 text-xs" x-on:change="$wire.placeItem(@js('section:'.$key), $event.target.value)">
                    <option value="">{{ __('lynx-admin::templates.section.move_to') }}</option>
                    @foreach ($places as $value => $placeLabel)
                        <option value="{{ $value }}">{{ $placeLabel }}</option>
                    @endforeach
                </select>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="{{ __('lynx-admin::templates.section.settings_of', ['section' => $sectionLabel]) }}" title="{{ __('lynx-admin::templates.section.settings') }}"><x-lucide-sliders-horizontal aria-hidden="true" /></x-lynx-admin::ui.button>
            @if ($canEdit) <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" class="hover:text-destructive" wire:click="removeSection({{ Js::from($key) }})" aria-label="{{ __('lynx-admin::templates.section.remove_named', ['section' => $sectionLabel]) }}" title="{{ __('lynx-admin::templates.section.remove') }}"><x-lucide-x aria-hidden="true" /></x-lynx-admin::ui.button> @endif
        </span>
    </div>
    <div x-show="open" x-cloak class="grid gap-3 border-t px-4 py-3 sm:grid-cols-2">
        <div class="grid gap-1 text-sm">
            <label for="section-{{ $key }}-label" class="font-medium">{{ __('lynx-admin::templates.section.label') }}</label>
            <x-lynx-admin::localized-input id="section-{{ $key }}-label" model="sections.{{ $key }}.label" />
        </div>
        <label class="flex items-center gap-2 self-end text-sm"><input class="blat-checkbox" type="checkbox" wire:model="sections.{{ $key }}.collapsed"> {{ __('lynx-admin::templates.section.collapsed') }}</label>
    </div>
    <ul @if ($canEdit) wire:sort="moveItem" wire:sort:group="layout" wire:sort:group-id="section:{{ $key }}" @endif
        class="bg-card divide-border mx-2 mb-2 grid min-h-12 content-start divide-y rounded-md border">
        @forelse ($sectionFields[$key] ?? [] as $field)
            @include('lynx-admin::livewire.partials.template-field-row', ['field' => $field])
        @empty
            <li class="text-muted-foreground p-3 text-center text-sm" wire:sort:ignore>{{ __('lynx-admin::templates.section.drop') }}</li>
        @endforelse
    </ul>
</li>
