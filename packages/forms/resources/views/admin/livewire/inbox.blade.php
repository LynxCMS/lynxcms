<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Search</span>
            <input type="search" class="blat-input w-56" wire:model.live.debounce.300ms="search" placeholder="Name, email, any answer">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">Show</span>
            <select class="blat-select w-40" wire:model.live="show">
                <option value="open">New and read</option>
                <option value="new">New</option>
                <option value="read">Read</option>
                <option value="archived">Archived</option>
                <option value="spam">Spam</option>
                <option value="all">All</option>
            </select>
        </label>
        @if (count($forms) > 1)
            <label class="grid gap-1 text-sm">
                <span class="text-muted-foreground">Form</span>
                <select class="blat-select w-48" wire:model.live="form">
                    <option value="">All forms</option>
                    @foreach ($forms as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @foreach ($forms as $key => $label)
            @if ($form === '' || $form === $key)
                <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.forms.export', $key)">Export {{ $label }} (CSV)</x-lynx-admin::ui.button>
            @endif
        @endforeach
    </div>

    @if ($selected !== [])
        <div class="flex flex-wrap items-center gap-2 text-sm" role="toolbar" aria-label="Selected submissions">
            <span>{{ count($selected) }} selected:</span>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('read')">Mark read</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('archived')">Archive</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="markSelected('spam')">Spam</x-lynx-admin::ui.button>
        </div>
    @endif

    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="w-10 px-4"><span class="sr-only">Select</span></x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Received</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">From</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Form</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Status</x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($submissions as $submission)
                <x-lynx-admin::ui.table-row wire:key="submission-{{ $submission->uuid }}" @class(['font-semibold' => $submission->status === 'new'])>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2"><input type="checkbox" class="blat-checkbox" value="{{ $submission->uuid }}" wire:model.live="selected" aria-label="Select"></x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-nowrap px-4 py-2 tabular-nums text-muted-foreground" title="{{ $submission->created_at->toDateTimeString() }}">{{ $submission->created_at->diffForHumans() }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                        <a href="{{ route('lynx.admin.forms.show', $submission->uuid) }}" class="hover:underline">{{ $this->summaryOf($submission) ?: 'Submission '.$submission->id }}</a>
                        @if ($submission->files->isNotEmpty())
                            <span class="text-muted-foreground text-xs font-normal">· {{ $submission->files->count() }} {{ \Illuminate\Support\Str::plural('file', $submission->files->count()) }}</span>
                        @endif
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2 font-normal text-muted-foreground">{{ $forms[$submission->form] ?? $submission->form }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 font-normal">
                        <x-lynx-admin::ui.badge :variant="$submission->status === 'new' ? 'default' : 'secondary'">{{ $submission->status }}</x-lynx-admin::ui.badge>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="5" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No submissions match these filters.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>

    @if ($page > 1 || $hasMore)
        <div class="flex items-center gap-2 text-sm">
            <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="$set('page', {{ $page - 1 }})" :disabled="$page <= 1">Newer</x-lynx-admin::ui.button>
            <span class="text-muted-foreground">Page {{ $page }} · {{ $total }} in all</span>
            <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="$set('page', {{ $page + 1 }})" :disabled="! $hasMore">Older</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
