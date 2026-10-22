<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <label class="flex items-center gap-2 self-center pt-5 text-sm">
            <input id="fields-show-system" type="checkbox" class="blat-checkbox" wire:model.live="showSystem">
            Show system fields
        </label>
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Search</span>
            <input id="fields-search" type="search" class="blat-input w-56" wire:model.live.debounce.250ms="search" placeholder="Name or label">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Type</span>
            <select id="fields-type" class="blat-select w-40" wire:model.live="type">
                <option value="">All types</option>
                @foreach ($types as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Template</span>
            <select id="fields-template" class="blat-select w-44" wire:model.live="template">
                <option value="">All templates</option>
                @foreach ($templates as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Name</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Label</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Type</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Used by</x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @forelse ($fields as $field)
                    <x-lynx-admin::ui.table-row wire:key="field-{{ $field->name }}">
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2"><a href="{{ route('lynx.admin.fields.edit', $field->name) }}" class="font-medium">{{ $field->name }}</a>@if ($owner = $owners->ownerOfField($field->name)) <x-lynx-admin::ui.badge variant="secondary" class="ms-1">system · {{ $owner }}</x-lynx-admin::ui.badge>@endif</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">{{ $field->label }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-muted-foreground">{{ $field->type }}{{ $field->isTranslatable() ? ', translatable' : '' }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 text-muted-foreground">{{ implode(', ', $usedBy[$field->name] ?? []) ?: 'No template' }}</x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @empty
                    <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="4" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No fields match these filters.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
                @endforelse
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
</div>
