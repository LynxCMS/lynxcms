{{-- "Select page…" in the link dialog: a search box, then the browsed page, a way up, and its children (or the pages found). --}}
<div class="rounded-lg border text-sm">
    <div class="border-b p-2">
        <label for="link-page-search" class="sr-only">{{ __('lynx-admin::link.search_pages') }}</label>
        <input id="link-page-search" type="search" class="blat-input" placeholder="{{ __('lynx-admin::link.search_placeholder') }}" autocomplete="off"
               wire:model.live.debounce.300ms="pageQuery" x-on:keydown.enter.prevent>
    </div>
    @if ($found !== null)
        <ul class="max-h-56 overflow-y-auto divide-y divide-border" aria-label="{{ __('lynx-admin::link.pages_matching', ['query' => $pageQuery]) }}" aria-live="polite">
            @forelse ($found as $match)
                <li>
                    <button type="button" wire:click="choosePage({{ $match['id'] }})" class="flex w-full items-center justify-between gap-4 px-3 py-1.5 text-start hover:bg-accent hover:text-accent-foreground">
                        <span>{{ $match['title'] }}</span>
                        <span class="text-muted-foreground truncate">{{ $match['url'] }}</span>
                    </button>
                </li>
            @empty
                <li class="text-muted-foreground px-3 py-2">{{ __('lynx-admin::link.no_pages_match') }}</li>
            @endforelse
        </ul>
    @else
    <div class="bg-muted/50 flex items-center gap-2 border-b px-3 py-2">
        @if ($browsing['parent'] !== null)
            <button type="button" wire:click="browse({{ $browsing['parent'] }})" class="text-muted-foreground hover:text-foreground" aria-label="{{ __('lynx-admin::link.up') }}">
                <x-lucide-arrow-up class="size-4" aria-hidden="true" />
            </button>
        @endif
        @if ($browsing['page']['url'] !== '')
            <button type="button" wire:click="choosePage({{ $browsing['page']['id'] }})" class="font-medium hover:underline">{{ $browsing['page']['title'] }}</button>
        @else
            <span class="font-medium">{{ $browsing['page']['title'] }}</span>
        @endif
    </div>
    <ul class="max-h-56 overflow-y-auto divide-y divide-border" aria-label="{{ __('lynx-admin::link.pages_under', ['page' => $browsing['page']['title']]) }}">
        @forelse ($browsing['children'] as $child)
            <li class="flex items-center justify-between gap-2 px-3 py-1.5">
                <button type="button" wire:click="choosePage({{ $child['id'] }})" class="text-start hover:underline">{{ $child['title'] }}</button>
                @if ($child['canHaveChildren'])
                    <button type="button" wire:click="browse({{ $child['id'] }})" class="text-muted-foreground hover:text-foreground" aria-label="{{ __('lynx-admin::link.pages_under', ['page' => $child['title']]) }}">
                        <x-lucide-chevron-right class="size-4 rtl:rotate-180" aria-hidden="true" />
                    </button>
                @endif
            </li>
        @empty
            <li class="text-muted-foreground px-3 py-2">{{ __('lynx-admin::link.no_pages_under') }}</li>
        @endforelse
    </ul>
    @endif
</div>
