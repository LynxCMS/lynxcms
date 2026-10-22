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

    {{-- Framed like BlatUI's tree table; dims while Livewire works. --}}
    <div class="overflow-hidden rounded-lg border bg-card shadow-xs" wire:loading.class.delay="opacity-60">
        <ul role="tree" aria-label="Pages" class="-mb-px">
            <x-lynx-admin::tree.branch
                :branch="$tree"
                :depth="0"
                :parent-position="0"
                :state="['moving' => $moving, 'deleting' => $deleting, 'deleteCount' => $deleteCount, 'sortable' => $sortable, 'events' => $events, 'trash' => $usesTrash]"
            />
        </ul>
    </div>
</div>
