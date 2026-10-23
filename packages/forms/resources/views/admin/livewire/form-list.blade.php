<div class="space-y-6">
    @if ($canBuild)
        <form wire:submit="create" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-4">
            <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Title</span>
                <input type="text" class="blat-input w-56" wire:model="label" placeholder="Newsletter">
                @error('label') <span role="alert" class="text-destructive text-xs">{{ $message }}</span> @enderror
            </label>
            <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Name (its key)</span>
                <input type="text" class="blat-input w-48 font-mono" wire:model="name" placeholder="newsletter">
                @error('name') <span role="alert" class="text-destructive text-xs">{{ $message }}</span> @enderror
            </label>
            <x-lynx-admin::ui.button type="submit">New form</x-lynx-admin::ui.button>
        </form>
    @endif

    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Form</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Name</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Submissions</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">Actions</span></x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($rows as $row)
                <x-lynx-admin::ui.table-row wire:key="form-{{ $row['key'] }}">
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                        <span class="font-medium">{{ $row['label'] }}</span>
                        @if ($row['kind'] === 'code')
                            <x-lynx-admin::ui.badge variant="secondary" class="ms-1">code</x-lynx-admin::ui.badge>
                        @elseif (! $row['published'])
                            <x-lynx-admin::ui.badge variant="outline" class="ms-1">unpublished</x-lynx-admin::ui.badge>
                        @endif
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 font-mono text-xs text-muted-foreground">{{ $row['key'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 tabular-nums">
                        <a href="{{ route('lynx.admin.forms', ['form' => $row['key'], 'show' => 'all']) }}" class="hover:underline">{{ $countOf($row['key']) }}</a>
                        @if ($countOf($row['key'], 'new') > 0) <span class="text-muted-foreground text-xs">({{ $countOf($row['key'], 'new') }} new)</span> @endif
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end">
                        <span class="inline-flex gap-2">
                            @if ($row['edit'] !== null && $canBuild)
                                <x-lynx-admin::ui.button variant="outline" size="xs" :href="$row['edit']">Edit</x-lynx-admin::ui.button>
                            @endif
                            <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.forms.results', $row['key'])">Results</x-lynx-admin::ui.button>
                            <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.forms.preview', $row['key'])">Preview</x-lynx-admin::ui.button>
                        </span>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="4" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No forms yet.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>
</div>
