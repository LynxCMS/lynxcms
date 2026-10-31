<x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::roles.list.name') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::roles.list.label') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">{{ __('lynx-admin::roles.list.grants') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">{{ __('lynx-admin::roles.list.users') }}</x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($rows as $name => $row)
                <x-lynx-admin::ui.table-row wire:key="role-{{ $name }}">
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2"><a href="{{ route('lynx.admin.roles.edit', $name) }}" class="font-medium">{{ $name }}</a>@if ($row['superuser']) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">{{ __('lynx-admin::roles.list.superuser') }}</x-lynx-admin::ui.badge>@endif</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $row['label'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end tabular-nums">{{ $row['superuser'] ? __('lynx-admin::roles.list.all') : $row['grants'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end tabular-nums">{{ $row['users'] }}</x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="4" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">{{ __('lynx-admin::roles.list.empty') }}</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>
