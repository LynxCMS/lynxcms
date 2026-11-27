{{-- The Insights screen's search and filters, kept in the address, and Write a report now. --}}
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-wrap items-end gap-3" role="search" data-insights-filters>
        <div class="grid gap-1 text-sm">
            <label for="insights-search" class="text-muted-foreground">{{ __('lynx-insights::admin.filters.search') }}</label>
            <input id="insights-search" type="search" class="blat-input w-56" wire:model.live.debounce.300ms="search" placeholder="{{ __('lynx-insights::admin.filters.search_placeholder') }}">
        </div>
        <div class="grid gap-1 text-sm">
            <label for="insights-state" class="text-muted-foreground">{{ __('lynx-insights::admin.filters.state') }}</label>
            <select id="insights-state" class="blat-select w-44" wire:model.live="state" data-insights-state>
                @foreach (\LynxCms\Insights\Admin\InsightFilter::states() as $option)
                    @php($count = $option === \LynxCms\Insights\Admin\InsightFilter::ALL ? array_sum($counts) : ($counts[$option] ?? 0))
                    <option value="{{ $option }}">{{ __('lynx-insights::admin.filters.states.'.$option, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($count)]) }}</option>
                @endforeach
            </select>
        </div>
        @if (count($sources) > 1)
            <div class="grid gap-1 text-sm">
                <label for="insights-source" class="text-muted-foreground">{{ __('lynx-insights::admin.filters.source') }}</label>
                <select id="insights-source" class="blat-select w-52" wire:model.live="source" data-insights-source>
                    <option value="">{{ __('lynx-insights::admin.filters.all_sources') }}</option>
                    @foreach ($sources as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="grid gap-1 text-sm">
            <label for="insights-report" class="text-muted-foreground">{{ __('lynx-insights::admin.filters.report') }}</label>
            <select id="insights-report" class="blat-select w-80" wire:model.live="report" data-insights-report>
                @foreach ($reports as $option)
                    <option value="{{ $loop->first ? '' : $option->id }}">{{ __($loop->first ? 'lynx-insights::admin.filters.latest' : 'lynx-insights::admin.period', ['start' => $option->period_start->isoFormat('ll'), 'end' => $option->period_end->isoFormat('ll')]) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    @if ($canReview)
        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="generate" data-insights-generate>{{ __('lynx-insights::admin.generate') }}</x-lynx-admin::ui.button>
    @endif
</div>
