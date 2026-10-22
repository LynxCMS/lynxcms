{{-- The icon picker: the chosen icon, and a grid of icons to search and choose from. --}}
<div class="grid gap-2" x-data="{ open: false }" x-on:keydown.escape="open = false">
    <div class="flex items-center gap-2">
        <button type="button" class="blat-input flex h-9 min-w-48 items-center gap-2 text-start" x-on:click="open = ! open" x-bind:aria-expanded="open" @disabled($disabled)>
            @if ($value !== '')
                @if (app(\LynxCms\Admin\Icons\IconSet::class)->has($value))<x-dynamic-component :component="'lucide-'.$value" class="size-4 shrink-0" aria-hidden="true" />@endif
                <span class="font-mono text-sm">{{ $value }}</span>
            @else
                <span class="text-muted-foreground text-sm">No icon</span>
            @endif
        </button>
        @if ($value !== '' && ! $disabled)
            <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" wire:click="clear">Remove</x-lynx-admin::ui.button>
        @endif
    </div>
    <div x-show="open" x-cloak class="bg-popover grid gap-2 rounded-md border p-2 shadow-sm">
        <input type="search" class="blat-input h-8 text-sm" wire:model.live.debounce.250ms="search" placeholder="Search icons, e.g. calendar" aria-label="Search icons">
        <ul class="grid max-h-64 grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-1 overflow-y-auto" aria-label="Icons">
            @forelse ($icons as $icon)
                <li wire:key="icon-{{ $icon }}">
                    <button type="button" data-icon-option="{{ $icon }}" title="{{ $icon }}" wire:click="choose(@js($icon))" x-on:click="open = false"
                            @class(['flex w-full flex-col items-center gap-1 rounded-md p-2 text-xs hover:bg-accent', 'bg-accent' => $icon === $value])>
                        <x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" />
                        <span class="w-full truncate text-center font-mono">{{ $icon }}</span>
                    </button>
                </li>
            @empty
                <li class="text-muted-foreground col-span-full p-2 text-sm">No icon matches.</li>
            @endforelse
        </ul>
    </div>
</div>
