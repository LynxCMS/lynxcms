{{--
    The layout builder (EditsTemplateLayout): tabs, each with a main and a sidebar list of fields
    and sections. Every list shares one wire:sort group, so items move between lists; the Place
    select does the same without a mouse. Saved with "Save fields".
--}}
@php
    $tabLabels = ['content' => __('lynx-admin::editor.content')] + collect($tabs)->mapWithKeys(fn ($t) => [$t['key'] => collect($t['label'])->filter()->first() ?? $t['key']])->all();
    $places = [];
    foreach ($tabLabels as $tabKey => $tabLabel) {
        $places["tab:{$tabKey}:main"] = $tabLabel.' · '.__('lynx-admin::editor.main');
        $places["tab:{$tabKey}:sidebar"] = $tabLabel.' · '.__('lynx-admin::editor.sidebar');
    }
    $sectionPlaces = collect($sections)->mapWithKeys(fn ($s, $key) => ["section:{$key}" => __('lynx-admin::templates.layout.in_section', ['section' => collect($s['label'])->filter()->first() ?? $key])])->all();
@endphp
<div x-data="{ tab: 'content' }" class="grid gap-4 p-5">
    <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="{{ __('lynx-admin::templates.layout.tabs') }}">
        @foreach ($tabLabels as $tabKey => $tabLabel)
            <span class="flex items-center">
                <button type="button" role="tab" class="rounded-md px-3 py-1.5 text-sm font-medium" x-bind:class="tab === @js($tabKey) ? 'bg-secondary text-secondary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        x-bind:aria-selected="tab === @js($tabKey)" x-on:click="tab = @js($tabKey)">{{ $tabLabel }}</button>
                @if ($canEdit && $tabKey !== 'content')
                    <button type="button" class="text-muted-foreground hover:text-destructive px-1" wire:click="removeTab(@js($tabKey))" aria-label="{{ __('lynx-admin::templates.layout.remove_tab', ['tab' => $tabLabel]) }}"><x-lucide-x class="size-3.5" aria-hidden="true" /></button>
                @endif
            </span>
        @endforeach
        @if ($canEdit)
            <span x-data="{ adding: false }" class="flex items-center gap-2">
                <button type="button" class="text-primary text-sm font-medium underline-offset-4 hover:underline" x-show="! adding" x-on:click="adding = true">{{ __('lynx-admin::templates.layout.add_tab') }}</button>
                <span x-show="adding" x-cloak class="flex items-center gap-2">
                    <x-lynx-admin::localized-input id="layout-new-tab" model="newTab" :placeholder="__('lynx-admin::templates.layout.tab_placeholder')" />
                    <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="addTab" x-on:click="adding = false">{{ __('lynx-admin::templates.layout.add') }}</x-lynx-admin::ui.button>
                </span>
            </span>
        @endif
    </div>
    @error('layout') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror

    @foreach ($tabLabels as $tabKey => $tabLabel)
        <div x-show="tab === @js($tabKey)" @if ($tabKey !== 'content') x-cloak @endif class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]" role="tabpanel" aria-label="{{ $tabLabel }}">
            @foreach (['main' => __('lynx-admin::editor.main'), 'sidebar' => __('lynx-admin::editor.sidebar')] as $column => $columnLabel)
                <div class="grid min-w-0 gap-2">
                    <h3 class="text-muted-foreground text-xs font-semibold uppercase tracking-wide">{{ $columnLabel }}</h3>
                    <ul @if ($canEdit) wire:sort="moveItem" wire:sort:group="layout" wire:sort:group-id="tab:{{ $tabKey }}:{{ $column }}" @endif
                        class="border-border grid min-h-14 content-start rounded-lg border border-dashed">
                        @forelse ($board[$tabKey][$column] ?? [] as $item)
                            @if (str_starts_with($item, 'field:'))
                                @include('lynx-admin::livewire.partials.template-field-row', ['field' => substr($item, 6)])
                            @else
                                @include('lynx-admin::livewire.partials.template-section', ['key' => substr($item, 8)])
                            @endif
                        @empty
                            <li class="text-muted-foreground p-4 text-center text-sm" wire:sort:ignore>{{ __('lynx-admin::templates.layout.drop') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    @endforeach

    @if ($canEdit)
        <div class="flex flex-wrap items-end gap-2">
            <div class="grid gap-1 text-sm">
                <label for="layout-new-section" class="font-medium">{{ __('lynx-admin::templates.layout.add_a_section') }}</label>
                <x-lynx-admin::localized-input id="layout-new-section" model="newSection" :placeholder="__('lynx-admin::templates.layout.section_placeholder')" />
            </div>
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="addSection">{{ __('lynx-admin::templates.layout.add_section') }}</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
