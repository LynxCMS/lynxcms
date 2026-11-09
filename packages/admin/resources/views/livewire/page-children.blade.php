{{-- The Children tab: the page's children to search, filter, reorder by drag, publish, hide and trash. --}}
@php($parent = lynx()->pages->get($pageId))
<section class="bg-card text-card-foreground rounded-xl border shadow-sm" aria-labelledby="children-heading" data-page-children>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5">
        <div class="flex items-baseline gap-2">
            <h2 id="children-heading" class="text-base font-semibold">{{ __('lynx-admin::children.heading') }}</h2>
            <span class="text-muted-foreground text-sm">{{ trans_choice('lynx-admin::children.count', $total) }}</span>
        </div>
        @if ($parent?->template()->allowsChildren())
            <x-lynx-admin::ui.button size="sm" variant="outline" :href="route('lynx.admin.pages.create', ['parent' => $pageId])"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::children.add') }}</x-lynx-admin::ui.button>
        @endif
    </div>

    <div class="flex flex-wrap items-end gap-3 border-b p-5">
        <div class="grid min-w-0 flex-1 gap-1 text-sm">
            <label for="children-search" class="sr-only">{{ __('lynx-admin::children.search') }}</label>
            <input id="children-search" type="search" class="blat-input" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-admin::children.search_placeholder') }}" autocomplete="off">
        </div>
        <div class="grid gap-1 text-sm">
            <label for="children-filter" class="font-medium">{{ __('lynx-admin::children.filter') }}</label>
            <select id="children-filter" class="blat-select w-40" wire:model.live="filter">
                @foreach ($filters as $value)
                    <option value="{{ $value }}">{{ __('lynx-admin::children.filters.'.$value) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($selected !== [])
        <div class="bg-muted/50 flex flex-wrap items-center gap-2 border-b px-5 py-3 text-sm" role="toolbar" aria-label="{{ trans_choice('lynx-admin::children.selected', count($selected)) }}">
            <span class="me-2 font-medium">{{ trans_choice('lynx-admin::children.selected', count($selected)) }}</span>
            @foreach (['publish', 'unpublish', 'hide', 'show'] as $action)
                <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="bulk('{{ $action }}')">{{ __('lynx-admin::children.'.$action) }}</x-lynx-admin::ui.button>
            @endforeach
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" class="hover:text-destructive" wire:click="confirmTrash({{ Js::from($selected) }})">{{ __('lynx-admin::children.trash') }}</x-lynx-admin::ui.button>
        </div>
    @endif

    @if ($trashing !== [])
        <div class="border-destructive/40 bg-destructive/5 flex flex-wrap items-center gap-3 border-b px-5 py-3 text-sm" role="alert">
            <span class="me-auto">{{ trans_choice('lynx-admin::children.confirm_trash', count($trashing)) }}</span>
            <x-lynx-admin::ui.button size="sm" variant="destructive" type="button" wire:click="trash">{{ __('lynx-admin::children.confirm') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="cancelTrash">{{ __('lynx-admin::children.cancel') }}</x-lynx-admin::ui.button>
        </div>
    @endif

    @if ($children->count() === 0)
        <p class="text-muted-foreground p-5 text-center text-sm">{{ __('lynx-admin::children.none') }}</p>
    @else
        <div class="text-muted-foreground flex items-center gap-3 border-b px-5 py-2 text-xs">
            <input type="checkbox" class="blat-checkbox" aria-label="{{ __('lynx-admin::children.select_all') }}"
                x-on:change="$wire.set('selected', $event.target.checked ? @js($children->ids()) : [])" @checked(count($selected) === $children->count())>
            @if (! $canReorder)
                <span>{{ $search !== '' || $filter !== 'all' ? __('lynx-admin::children.reorder_hint') : __('lynx-admin::children.incomplete_hint') }}</span>
            @endif
        </div>
        <ul class="divide-y" @if ($canReorder) wire:sort="reorder" @endif>
            @foreach ($children as $child)
                @include('lynx-admin::livewire.partials.page-child-row', ['child' => $child])
            @endforeach
        </ul>
    @endif

    @if ($lastPage > 1)
        <nav class="flex items-center justify-between gap-3 border-t px-5 py-3 text-sm" aria-label="{{ __('lynx-admin::children.page_of', ['page' => $page, 'last' => $lastPage]) }}">
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">{{ __('lynx-admin::children.previous') }}</x-lynx-admin::ui.button>
            <span class="text-muted-foreground">{{ __('lynx-admin::children.page_of', ['page' => $page, 'last' => $lastPage]) }}</span>
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">{{ __('lynx-admin::children.next') }}</x-lynx-admin::ui.button>
        </nav>
    @endif
</section>
