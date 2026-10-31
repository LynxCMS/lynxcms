<div class="space-y-4">
    <label class="flex w-fit items-center gap-2 text-sm">
        <input id="templates-show-system" type="checkbox" class="blat-checkbox" wire:model.live="showSystem">
        {{ __('lynx-admin::templates.list.show_system') }} <span class="text-muted-foreground">{{ __('lynx-admin::templates.list.system_note') }}</span>
    </label>

<x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::templates.list.name') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::templates.list.label') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">{{ __('lynx-admin::templates.list.fields') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-end text-muted-foreground">{{ __('lynx-admin::templates.list.pages') }}</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">{{ __('lynx-admin::templates.list.flags') }}</x-lynx-admin::ui.table-head>
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
                        {{ implode(', ', array_keys(array_filter([__('lynx-admin::templates.list.no_url') => ! $row['has_url'], __('lynx-admin::templates.list.singleton') => $row['singleton']]))) }}
                        @if ($row['owner']) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">{{ __('lynx-admin::templates.list.system', ['owner' => $row['owner']]) }}</x-lynx-admin::ui.badge> @endif
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @endforeach
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>
</div>
