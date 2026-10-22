<div class="space-y-4">
    <label class="flex w-fit items-center gap-2 text-sm">
        <input id="templates-show-system" type="checkbox" class="blat-checkbox" wire:model.live="showSystem">
        Show system templates <span class="text-muted-foreground">(Lynx's and modules', read only)</span>
    </label>

<x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Name</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Label</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">Fields</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">Pages</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Flags</x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @foreach ($rows as $name => $row)
                <x-lynx-admin::ui.table-row wire:key="template-{{ $name }}">
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2"><a href="{{ route('lynx.admin.templates.edit', $name) }}" class="font-medium">{{ $name }}</a></x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $row['label'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end tabular-nums">{{ $row['fields'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-end tabular-nums">{{ $row['pages'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-muted-foreground">
                        {{ implode(', ', array_keys(array_filter(['no URL' => ! $row['has_url'], 'singleton' => $row['singleton']]))) }}
                        @if ($row['owner']) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">system · {{ $row['owner'] }}</x-lynx-admin::ui.badge> @endif
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @endforeach
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>
</div>
