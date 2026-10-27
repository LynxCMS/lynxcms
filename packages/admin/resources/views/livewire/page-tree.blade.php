<div
    class="lynx-tree"
    x-data="{ selected: null }"
    @if ($moving) @keydown.escape.window="$wire.cancelMove()" @endif
>
    @if ($error)
        <x-lynx-admin::ui.alert variant="destructive" class="mb-4">
            <x-lucide-circle-alert aria-hidden="true" />
            <x-lynx-admin::ui.alert-description>{{ $error }}</x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endif

    @if ($trashReport)
        <x-lynx-admin::ui.alert tone="neutral" role="status" class="mb-4">
            <x-lucide-trash-2 aria-hidden="true" />
            <x-lynx-admin::ui.alert-title>Deleted {{ $trashReport['deleted'] }} {{ Str::plural('branch', $trashReport['deleted']) }}.</x-lynx-admin::ui.alert-title>
            <x-lynx-admin::ui.alert-description>
                @if ($trashReport['blocked'] !== [])
                    <p>Kept because a page is locked:</p>
                    <ul class="list-disc ps-5">
                        @foreach ($trashReport['blocked'] as $branch)
                            <li>{{ $branch['title'] }}: unlock
                                @foreach ($branch['locked'] as $locked)
                                    <a href="{{ route('lynx.admin.pages.edit', $locked['id']) }}" class="font-medium underline underline-offset-4">{{ $locked['title'] }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($trashReport['denied'] > 0)
                    <p>Kept {{ $trashReport['denied'] }} {{ Str::plural('branch', $trashReport['denied']) }} you may not delete.</p>
                @endif
                <x-lynx-admin::ui.button variant="ghost" size="xs" class="-ms-2" wire:click="closeTrashReport">Close</x-lynx-admin::ui.button>
            </x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endif

    @if ($moving)
        <x-lynx-admin::ui.alert tone="info" role="status" class="mb-4">
            <x-lucide-move aria-hidden="true" />
            <x-lynx-admin::ui.alert-description class="flex flex-wrap items-center gap-3">
                Drag the highlighted page by its handle, or use the arrow keys (← → change level). Esc or Enter ends move mode.
                <x-lynx-admin::ui.button size="xs" wire:click="cancelMove">Done</x-lynx-admin::ui.button>
            </x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endif

    @if ($canShowDetails)
        {{-- Simple shows titles; Details adds languages, status and the last change to each row, and filters (TreeFilters). --}}
        <div class="mb-3 flex flex-wrap items-center justify-end gap-3">
            @if ($filters !== [])
                <div role="group" aria-label="Show" class="me-auto flex flex-wrap gap-1.5">
                    @foreach ([['key' => '', 'label' => 'All pages', 'count' => null], ...$filters] as $item)
                        <button type="button" wire:click="filterBy('{{ $item['key'] }}')" aria-pressed="{{ $filter === $item['key'] ? 'true' : 'false' }}"
                                @if ($item['key'] !== '') data-tree-filter="{{ $item['key'] }}" data-filter-count="{{ $item['count'] }}" @endif
                                class="group text-muted-foreground hover:text-foreground aria-pressed:bg-foreground aria-pressed:text-background aria-pressed:border-foreground inline-flex h-7 items-center gap-1.5 rounded-full border px-3 text-sm">
                            {{ $item['label'] }}
                            @if ($item['count'] !== null)<b class="text-foreground group-aria-pressed:text-background font-semibold tabular-nums">{{ $item['count'] }}</b>@endif
                        </button>
                    @endforeach
                </div>
            @endif
            <div role="group" aria-label="Tree view" data-tree-view class="bg-muted inline-flex gap-0.5 rounded-md p-0.5 text-sm">
                @foreach ([false => 'Simple', true => 'Details'] as $value => $label)
                    <button type="button" wire:click="showDetails({{ $value ? 'true' : 'false' }})" aria-pressed="{{ $showsDetails === (bool) $value ? 'true' : 'false' }}"
                            class="text-muted-foreground aria-pressed:bg-background aria-pressed:text-foreground rounded-sm px-2.5 py-1 font-medium aria-pressed:shadow-xs">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Framed like BlatUI's tree table; dims while Livewire works. --}}
    <div class="overflow-hidden rounded-lg border bg-card shadow-xs" wire:loading.class.delay="opacity-60">
        @if ($showsDetails)
            <div class="text-muted-foreground bg-muted/50 hidden items-center gap-4 border-b px-4 py-2 text-xs font-medium md:flex" data-tree-details aria-hidden="true">
                <span>Page</span>
                <span class="ms-auto w-56">Languages</span><span class="w-28">Status</span><span class="w-48">Last change</span>
            </div>
        @endif
        @php($state = ['moving' => $moving, 'deleting' => $deleting, 'deleteCount' => $deleteCount, 'sortable' => $sortable, 'events' => $events, 'trash' => $usesTrash])
        @if ($filtered !== null)
            {{-- A filter's pages, flat and paged; nothing to reorder here. --}}
            <div role="tree" aria-label="Pages" class="-mb-px" data-tree-filtered>
                @if ($filtered->total === 0)
                    <p class="text-muted-foreground px-4 py-6 text-sm">No pages here.</p>
                @else
                    <x-lynx-admin::tree.level :level="$filtered" :depth="0" :parent-position="0" :state="[...$state, 'sortable' => false]" />
                @endif
            </div>
        @else
            <ul role="tree" aria-label="Pages" class="-mb-px">
                <x-lynx-admin::tree.branch :branch="$tree" :depth="0" :parent-position="0" :state="$state" />
            </ul>
        @endif
    </div>
</div>
