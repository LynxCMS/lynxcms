<x-lynx-admin::ui.table variant="card">
    <x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-row class="hover:bg-transparent">
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::languages.list.code') }}</x-lynx-admin::ui.table-head>
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::languages.list.name') }}</x-lynx-admin::ui.table-head>
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::languages.list.tab_label') }}</x-lynx-admin::ui.table-head>
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::languages.list.prefix') }}</x-lynx-admin::ui.table-head>
            <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::languages.list.fallback') }}</x-lynx-admin::ui.table-head>
        </x-lynx-admin::ui.table-row>
    </x-lynx-admin::ui.table-header>
    <x-lynx-admin::ui.table-body>
        @foreach ($rows as $row)
            <x-lynx-admin::ui.table-row wire:key="language-{{ $row['code'] }}">
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                    <a href="{{ route('lynx.admin.languages.edit', $row['code']) }}" class="font-mono font-medium">{{ $row['code'] }}</a>
                    @if ($row['default']) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">{{ __('lynx-admin::languages.list.default') }}</x-lynx-admin::ui.badge>@endif
                    @unless ($row['active']) <x-lynx-admin::ui.badge variant="outline" class="ms-1">{{ __('lynx-admin::languages.list.inactive') }}</x-lynx-admin::ui.badge>@endunless
                </x-lynx-admin::ui.table-cell>
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $row['name'] }}</x-lynx-admin::ui.table-cell>
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $row['label'] }}</x-lynx-admin::ui.table-cell>
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-mono text-sm">/{{ $row['prefix'] !== '' ? $row['prefix'].'/' : '' }}</x-lynx-admin::ui.table-cell>
                <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-mono text-sm">{{ $row['fallback'] ?? ($row['default'] ? '' : __('lynx-admin::languages.list.default_language')) }}</x-lynx-admin::ui.table-cell>
            </x-lynx-admin::ui.table-row>
        @endforeach
    </x-lynx-admin::ui.table-body>
</x-lynx-admin::ui.table>
