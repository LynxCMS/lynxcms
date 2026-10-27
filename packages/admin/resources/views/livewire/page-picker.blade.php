{{-- Search first, so the box sits under the field's label whatever is chosen. Results float over the page from <body>,
     anchored to the box, so a card's overflow never cuts them off; they flip above it when there is no room below.
     Being outside the box's tab order, the results are reached with the arrow keys; Escape returns to the box. --}}
<div class="grid gap-2">
    <div x-data="{ open: true }" x-on:keydown.escape.window="open = false">
        <input type="search" class="blat-input" x-ref="search" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.down.prevent="open = true; $nextTick(() => $refs.results?.querySelector('button')?.focus())" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder ?? ($multiple || $chosen === [] ? 'Search pages by title to add' : 'Search to replace the chosen page') }}" autocomplete="off" aria-label="Search pages">
        @if ($results !== [])
            @teleport('body')
            <ul data-picker-results x-ref="results" x-show="open" x-on:keydown.down.prevent="$focus.within($refs.results).wrap().next()" x-on:keydown.up.prevent="$focus.within($refs.results).wrap().previous()" x-on:keydown.escape.stop="$refs.search.focus()" x-blat-anchor.bottom-start.offset.4.match-width="$refs.search" x-on:click.outside="if (! $refs.search.contains($event.target)) open = false"
                class="bg-popover text-popover-foreground divide-border fixed z-50 max-h-72 w-80 max-w-[calc(100vw-2rem)] divide-y overflow-y-auto rounded-md border shadow-md" aria-label="Matching pages">
                @foreach ($results as $page)
                    <li>
                        <button type="button" wire:click="add({{ $page['id'] }})" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent">
                            <span class="min-w-0 flex-1">{{ $page['title'] }}</span>
                            <span class="text-muted-foreground max-w-[45%] shrink-0 truncate text-xs">{{ $page['url'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            @endteleport
        @endif
    </div>
    @if ($chosen !== [])
        <ul class="flex flex-wrap gap-2" aria-label="Chosen pages">
            @foreach ($chosen as $page)
                <li class="bg-secondary text-secondary-foreground inline-flex items-center gap-1 rounded-md border py-0.5 ps-2.5 pe-1 text-sm">
                    <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="font-medium underline-offset-4 hover:underline">{{ $page['title'] }}</a>
                    <button type="button" wire:click="remove({{ $page['id'] }})" class="text-muted-foreground hover:text-destructive flex size-5 items-center justify-center rounded-sm" aria-label="Remove {{ $page['title'] }}"><x-lucide-x class="size-3.5" aria-hidden="true" /></button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
