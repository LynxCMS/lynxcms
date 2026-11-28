{{-- The Insights screen (InsightsScreen): a report's insights, found by a search and filtered by state, source and report. --}}
<div class="grid grid-cols-1 gap-6" data-insights @if ($isRunning) wire:poll.3s="refreshBadge" @endif>
    @if ($shownReport === null)
        <x-lynx-admin::empty-state icon="lightbulb" :title="__('lynx-insights::admin.empty.title')" :help="__('lynx-insights::admin.empty.help')" data-insights-empty>
            @if ($canReview)
                <x-lynx-admin::ui.button type="button" wire:click="generate" data-insights-generate>{{ __('lynx-insights::admin.generate') }}</x-lynx-admin::ui.button>
            @endif
        </x-lynx-admin::empty-state>
    @else
        @include('lynx-insights::admin.filters')

        @foreach ($shownReport->failures as $failure)
            <x-lynx-admin::ui.alert variant="destructive" data-insights-failure="{{ $failure['source'] }}">
                <x-lynx-admin::ui.alert-description>{{ __('lynx-insights::admin.failed', ['source' => $labels[$failure['source']] ?? $failure['source'], 'error' => $failure['error']]) }}</x-lynx-admin::ui.alert-description>
            </x-lynx-admin::ui.alert>
        @endforeach

        <div class="grid gap-4" wire:loading.class="opacity-60" wire:target="search, state, source, report">
            @forelse ($insights as $insight)
                @include('lynx-insights::admin.insight', ['insight' => $insight, 'links' => $pages[$insight->id] ?? []])
            @empty
                @if ($filter->isFiltered())
                    <x-lynx-admin::empty-state icon="search-x" :title="__('lynx-insights::admin.no_match.title')" :help="__('lynx-insights::admin.no_match.help')" data-insights-no-match>
                        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="clearFilters" data-insights-clear>{{ __('lynx-insights::admin.filters.clear') }}</x-lynx-admin::ui.button>
                    </x-lynx-admin::empty-state>
                @else
                    <x-lynx-admin::empty-state icon="lightbulb" :title="__('lynx-insights::admin.none.title')" :help="__('lynx-insights::admin.none.help')" data-insights-none />
                @endif
            @endforelse
        </div>
    @endif
</div>
