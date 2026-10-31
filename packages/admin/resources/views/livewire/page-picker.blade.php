{{-- Search first, so the box sits under the field's label whatever is chosen. Results float over the page from <body>,
     anchored to the box, so a card's overflow never cuts them off; they flip above it when there is no room below.
     Being outside the box's tab order, the results are reached with the arrow keys; Escape returns to the box. --}}
<div class="grid gap-2">
    @if ($isFull)
        <p class="text-muted-foreground text-sm" data-picker-full>{{ __('lynx-admin::pages.picker.full', ['max' => $max]) }}</p>
    @else
        <div x-data="{ open: true }" x-on:keydown.escape.window="open = false">
            <input type="search" class="blat-input" x-ref="search" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.down.prevent="open = true; $nextTick(() => $refs.results?.querySelector('button')?.focus())" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder ?? __($multiple || $chosen === [] ? 'lynx-admin::pages.picker.search_add' : 'lynx-admin::pages.picker.search_replace') }}" autocomplete="off" aria-label="{{ __('lynx-admin::pages.picker.search') }}">
            @if ($results !== [])
                @teleport('body')
                <ul data-picker-results x-ref="results" x-show="open" x-on:keydown.down.prevent="$focus.within($refs.results).wrap().next()" x-on:keydown.up.prevent="$focus.within($refs.results).wrap().previous()" x-on:keydown.escape.stop="$refs.search.focus()" x-blat-anchor.bottom-start.offset.4.match-width="$refs.search" x-on:click.outside="if (! $refs.search.contains($event.target)) open = false"
                    class="bg-popover text-popover-foreground divide-border fixed z-50 max-h-72 w-80 max-w-[calc(100vw-2rem)] divide-y overflow-y-auto rounded-md border shadow-md" aria-label="{{ __('lynx-admin::pages.picker.matching') }}">
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
    @endif
    {{-- The chosen pages in the order templates read them: drag a row by its handle, or use its arrows. The list is
         always there, hidden while empty, so wire:sort is set up when the picker loads, not when the first page is added. --}}
    @php($isSortable = $multiple && count($chosen) > 1)
    <ul @class(['divide-y overflow-hidden rounded-md border', 'hidden' => $chosen === []]) aria-label="{{ __('lynx-admin::pages.picker.chosen') }}" @if ($multiple) wire:sort="sortPage" @endif>
            @foreach ($chosen as $page)
                <li wire:key="chosen-{{ $page['id'] }}" @if ($multiple) wire:sort:item="{{ $page['id'] }}" @endif class="bg-card flex items-center gap-2 px-2 py-1.5 text-sm" data-chosen-page="{{ $page['id'] }}">
                    @if ($isSortable)
                        <span wire:sort:handle class="text-muted-foreground cursor-grab select-none" title="{{ __('lynx-admin::pages.picker.drag') }}" aria-hidden="true"><x-lucide-grip-vertical class="size-4" /></span>
                    @endif
                    <span class="min-w-0 flex-1">
                        <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="font-medium underline-offset-4 hover:underline">{{ $page['title'] }}</a>
                        <span class="text-muted-foreground ms-2 truncate text-xs">{{ $page['url'] }}</span>
                    </span>
                    @if ($isSortable)
                        <button type="button" wire:click="movePage({{ $page['id'] }}, -1)" @disabled($loop->first) class="text-muted-foreground hover:text-foreground flex size-6 items-center justify-center rounded-sm disabled:opacity-30" aria-label="{{ __('lynx-admin::pages.picker.move_up', ['page' => $page['title']]) }}"><x-lucide-arrow-up class="size-3.5" aria-hidden="true" /></button>
                        <button type="button" wire:click="movePage({{ $page['id'] }}, 1)" @disabled($loop->last) class="text-muted-foreground hover:text-foreground flex size-6 items-center justify-center rounded-sm disabled:opacity-30" aria-label="{{ __('lynx-admin::pages.picker.move_down', ['page' => $page['title']]) }}"><x-lucide-arrow-down class="size-3.5" aria-hidden="true" /></button>
                    @endif
                    <button type="button" wire:click="remove({{ $page['id'] }})" class="text-muted-foreground hover:text-destructive flex size-6 items-center justify-center rounded-sm" aria-label="{{ __('lynx-admin::pages.picker.remove', ['page' => $page['title']]) }}"><x-lucide-x class="size-3.5" aria-hidden="true" /></button>
                </li>
            @endforeach
        </ul>
</div>
