{{-- The fields shown as result columns: a menu of checkboxes, up to FinderQuery::MAX_COLUMNS. --}}
@php
    $chosen = $search['columns'];
    $max = \LynxCms\Finder\Search\FinderQuery::MAX_COLUMNS;
@endphp
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.stop="open = false" x-on:click.outside="open = false" data-finder-columns>
    <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="finder-columns-menu" data-finder-columns-toggle>
        <x-lucide-columns-3 aria-hidden="true" /> {{ __('lynx-finder::admin.results.columns') }}
        @if ($chosen !== [])
            <span class="text-muted-foreground tabular-nums">{{ count($chosen) }}</span>
        @endif
    </x-lynx-admin::ui.button>
    <div id="finder-columns-menu" class="bg-popover text-popover-foreground absolute start-0 top-full sm:start-auto sm:end-0 z-20 mt-1 grid w-64 max-w-[calc(100vw-2rem)] gap-2 rounded-md border p-3 shadow-md" x-show="open" x-cloak wire:ignore.self>
        <p class="text-muted-foreground text-xs">{{ __('lynx-finder::admin.results.columns_help', ['max' => $max]) }}</p>
        @if ($catalog->columns() === [])
            <p class="text-sm">{{ __('lynx-finder::admin.results.no_columns') }}</p>
        @else
            <ul class="-mx-1 grid max-h-72 gap-0.5 overflow-y-auto">
                @foreach ($catalog->columns() as $field)
                    @php($checked = in_array($field->key, $chosen, true))
                    <li>
                        <label class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-sm px-1 py-1.5 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-50">
                            <input type="checkbox" class="blat-checkbox" value="{{ $field->key }}" wire:model.live="search.columns" @disabled(! $checked && count($chosen) >= $max) data-finder-column-choice="{{ $field->key }}">
                            <span class="min-w-0 truncate">{{ $field->label }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
