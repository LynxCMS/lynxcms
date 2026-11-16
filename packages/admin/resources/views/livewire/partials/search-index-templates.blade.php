{{-- Templates of the Search index screen with the fields each indexes; withOwner adds the module or Lynx that owns them (ADR-0115). --}}
<x-lynx-admin::ui.table variant="card">
    <x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-row class="hover:bg-transparent">
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.template') }}</x-lynx-admin::ui.table-head>
            @if ($withOwner)
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.owner') }}</x-lynx-admin::ui.table-head>
            @endif
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.pages') }}</x-lynx-admin::ui.table-head>
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::search_index.templates.fields') }}</x-lynx-admin::ui.table-head>
        </x-lynx-admin::ui.table-row>
    </x-lynx-admin::ui.table-header>
    <x-lynx-admin::ui.table-body>
        @foreach ($rows as $template)
            <x-lynx-admin::ui.table-row wire:key="search-template-{{ $template['name'] }}" data-template="{{ $template['name'] }}">
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                    {{ $template['label'] }} <span class="text-muted-foreground font-mono text-sm">{{ $template['name'] }}</span>
                    @unless ($template['has_url'])
                        <x-lynx-admin::ui.badge variant="outline" class="ms-1">{{ __('lynx-admin::search_index.templates.no_url') }}</x-lynx-admin::ui.badge>
                    @endunless
                </x-lynx-admin::ui.table-cell>
                @if ($withOwner)
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-mono text-sm">{{ $template['owner'] }}</x-lynx-admin::ui.table-cell>
                @endif
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $template['pages'] }}</x-lynx-admin::ui.table-cell>
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-mono text-sm">{{ implode(', ', $template['fields']) }}</x-lynx-admin::ui.table-cell>
            </x-lynx-admin::ui.table-row>
        @endforeach
    </x-lynx-admin::ui.table-body>
</x-lynx-admin::ui.table>
