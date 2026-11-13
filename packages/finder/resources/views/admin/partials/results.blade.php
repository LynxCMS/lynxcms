{{-- The pages found: count, order, field columns, a row per page linking to its editor, and paging. --}}
<section class="grid grid-cols-1 gap-3" aria-labelledby="finder-results-heading" wire:loading.class="opacity-60" data-finder-results="{{ $total }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="finder-results-heading" class="text-base font-semibold">
            {{ __('lynx-finder::admin.results.heading') }}
            <span class="text-muted-foreground ms-1 text-sm font-normal tabular-nums">{{ trans_choice('lynx-finder::admin.results.count', $total) }}</span>
        </h2>
        <div class="flex flex-wrap items-center gap-2">
            @include('lynx-finder::admin.partials.columns')
            <label for="finder-sort" class="blat-label">{{ __('lynx-finder::admin.results.sort') }}</label>
            <select id="finder-sort" class="blat-select w-auto" wire:model.live="search.sort" data-finder-sort>
                @foreach ($sorts as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @include('lynx-finder::admin.partials.bulk')

    @if ($rows === [])
        <x-lynx-admin::empty-state icon="search" :title="__('lynx-finder::admin.results.empty')" :help="__('lynx-finder::admin.results.empty_help')" />
    @else
        <div class="bg-card text-card-foreground overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="text-muted-foreground border-b text-start text-xs">
                    <tr>
                        <th scope="col" class="w-10 ps-4 py-2.5">
                            <input type="checkbox" class="blat-checkbox" aria-label="{{ __('lynx-admin::children.select_all') }}" data-finder-select-all
                                x-on:change="$wire.set('selected', $event.target.checked ? @js(array_column($rows, 'id')) : [])" @checked(count(array_intersect($selected, array_column($rows, 'id'))) === count($rows))>
                        </th>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('lynx-finder::admin.results.title') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('lynx-finder::admin.results.template') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('lynx-finder::admin.results.status') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('lynx-finder::admin.results.changed') }}</th>
                        @foreach ($columns as $column)
                            <th scope="col" class="px-4 py-2.5 text-start font-medium" data-finder-column="{{ $column->key }}">{{ $column->label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($rows as $row)
                        <tr wire:key="finder-row-{{ $row['id'] }}" data-finder-row="{{ $row['id'] }}">
                            <td class="ps-4 py-2.5"><input type="checkbox" class="blat-checkbox" value="{{ $row['id'] }}" wire:model.live="selected" aria-label="{{ __('lynx-finder::admin.bulk.select', ['title' => $row['title']]) }}"></td>
                            <td class="min-w-48 px-4 py-2.5"><a href="{{ $row['url'] }}" wire:navigate class="font-medium underline-offset-4 hover:underline">{{ $row['title'] }}</a></td>
                            <td class="text-muted-foreground px-4 py-2.5">{{ $row['template'] }}</td>
                            <td class="px-4 py-2.5">
                                <span class="flex flex-wrap gap-1">
                                    @foreach ($row['statuses'] as $status)
                                        <x-lynx-admin::ui.badge :variant="$status['key'] === 'published' ? 'secondary' : 'outline'" data-finder-status="{{ $status['key'] }}">{{ $status['label'] }}</x-lynx-admin::ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="text-muted-foreground px-4 py-2.5 whitespace-nowrap tabular-nums">
                                @if ($row['changed'] !== null)
                                    <time title="{{ $row['changedAt'] }}">{{ $row['changed'] }}</time>
                                @endif
                            </td>
                            @foreach ($columns as $column)
                                <td class="max-w-64 px-4 py-2.5 @if (in_array($column->kind->value, ['number', 'date'], true)) whitespace-nowrap tabular-nums @endif" data-finder-cell="{{ $row['id'] }}-{{ $column->key }}">{{ $row['cells'][$column->key] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($total > $perPage)
            <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="{{ __('lynx-finder::admin.results.heading') }}" data-finder-paging>
                <span class="text-muted-foreground tabular-nums">{{ __('lynx-finder::admin.results.range', ['from' => $start + 1, 'to' => min($start + $perPage, $total), 'total' => $total]) }}</span>
                <span class="flex gap-2">
                    <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="previous" :disabled="$start === 0" data-finder-previous>{{ __('lynx-finder::admin.results.previous') }}</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="next" :disabled="$start + $perPage >= $total" data-finder-next>{{ __('lynx-finder::admin.results.next') }}</x-lynx-admin::ui.button>
                </span>
            </nav>
        @endif
    @endif
</section>
