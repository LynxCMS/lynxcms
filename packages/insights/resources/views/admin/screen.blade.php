{{-- The Insights screen (InsightsScreen): the latest report's insights, heaviest first, then the earlier reports. --}}
<div class="grid grid-cols-1 gap-6" data-insights>
    @if ($report === null)
        <x-lynx-admin::empty-state icon="lightbulb" :title="__('lynx-insights::admin.empty.title')" :help="__('lynx-insights::admin.empty.help')" data-insights-empty>
            @if ($canReview)
                <x-lynx-admin::ui.button type="button" wire:click="generate" data-insights-generate>{{ __('lynx-insights::admin.generate') }}</x-lynx-admin::ui.button>
            @endif
        </x-lynx-admin::empty-state>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-muted-foreground text-sm" data-insights-period>{{ __('lynx-insights::admin.period', ['start' => $report->period_start->isoFormat('LL'), 'end' => $report->period_end->isoFormat('LL')]) }}</p>
            @if ($canReview)
                <x-lynx-admin::ui.button variant="outline" size="sm" type="button" wire:click="generate" data-insights-generate>{{ __('lynx-insights::admin.generate') }}</x-lynx-admin::ui.button>
            @endif
        </div>

        @foreach ($report->failures as $failure)
            <x-lynx-admin::ui.alert variant="destructive" data-insights-failure="{{ $failure['source'] }}">
                <x-lynx-admin::ui.alert-description>{{ __('lynx-insights::admin.failed', ['source' => $failure['source'], 'error' => $failure['error']]) }}</x-lynx-admin::ui.alert-description>
            </x-lynx-admin::ui.alert>
        @endforeach

        @forelse ($insights as $insight)
            @include('lynx-insights::admin.insight', ['insight' => $insight, 'links' => $pages[$insight->id] ?? []])
        @empty
            <x-lynx-admin::empty-state icon="lightbulb" :title="__('lynx-insights::admin.none.title')" :help="__('lynx-insights::admin.none.help')" data-insights-none />
        @endforelse

        @if ($earlier !== [])
            <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="insights-earlier" data-insights-earlier>
                <h2 id="insights-earlier" class="mb-4 text-base font-semibold">{{ __('lynx-insights::admin.earlier') }}</h2>
                <ul class="divide-y divide-border text-sm">
                    @foreach ($earlier as $entry)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2" wire:key="report-{{ $entry['report']->id }}" data-insights-report="{{ $entry['report']->id }}">
                            <span>{{ __('lynx-insights::admin.period', ['start' => $entry['report']->period_start->isoFormat('LL'), 'end' => $entry['report']->period_end->isoFormat('LL')]) }}</span>
                            <span class="text-muted-foreground flex flex-wrap gap-x-3">
                                @foreach ($entry['counts'] as $state => $count)
                                    <span data-insights-count="{{ $state }}">{{ trans_choice('lynx-insights::admin.states.'.$state, $count, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($count)]) }}</span>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
