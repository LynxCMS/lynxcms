{{-- One insight on the Insights screen: its wording, evidence and pages, and, while open, the review and the decisions. --}}
@php($isOpen = $insight->state === \LynxCms\Insights\InsightState::Open)
@php($steps = $insight->steps ?? [])
@php($failed = \LynxCms\Insights\PlanSteps::count($steps, \LynxCms\Insights\PlanSteps::FAILED))
{{-- While an approval runs, or after a step failed, the step list names the pages instead. --}}
@php($showsSteps = $insight->state === \LynxCms\Insights\InsightState::Running || ($isOpen && $failed > 0))
<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="insight-{{ $insight->id }}" wire:key="insight-{{ $insight->id }}" data-insight="{{ $insight->id }}" data-insight-state="{{ $insight->state->value }}">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="insight-{{ $insight->id }}" class="text-base font-semibold">{{ $insight->title }}</h2>
        <span class="flex flex-wrap gap-2">
            <x-lynx-admin::ui.badge variant="outline" data-insight-source>{{ $labels[$insight->source] ?? $insight->source }}</x-lynx-admin::ui.badge>
            @if ($insight->state === \LynxCms\Insights\InsightState::Running)
                <x-lynx-admin::ui.badge variant="secondary" data-insight-running>
                    <x-lynx-admin::ui.presence status="online" size="sm" pulse :label="__('lynx-insights::admin.state.running')" show-label label-class="text-xs" />
                </x-lynx-admin::ui.badge>
            @elseif (! $isOpen)
                <x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-insights::admin.state.'.$insight->state->value) }}</x-lynx-admin::ui.badge>
            @endif
        </span>
    </div>
    <p class="text-muted-foreground text-sm mb-4">{{ $insight->reason }}</p>

    @if ($insight->evidence !== [])
        <dl class="mb-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm" data-insight-evidence>
            @foreach ($insight->evidence as $fact)
                <dt class="text-muted-foreground">{{ $fact['label'] }}</dt>
                <dd>{{ is_int($fact['value']) || is_float($fact['value']) ? \LynxCms\Core\Support\LocalizedNumber::format($fact['value']) : $fact['value'] }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($links !== [] && ! $showsSteps)
        @php($first = array_slice($links, 0, 6))
        @php($rest = array_slice($links, 6))
        <div class="mb-4 text-sm" data-insight-pages>
            <p class="text-muted-foreground mb-1">{{ trans_choice('lynx-insights::admin.pages', count($links), ['count' => \LynxCms\Core\Support\LocalizedNumber::format(count($links))]) }}</p>
            <p class="flex flex-wrap gap-x-3 gap-y-1">
                @foreach ($first as $link)
                    <a href="{{ route('lynx.admin.pages.edit', $link['id']) }}" class="text-primary underline-offset-4 hover:underline" data-insight-page="{{ $link['id'] }}">{{ $link['title'] }}</a>
                @endforeach
            </p>
            @if ($rest !== [])
                <details class="mt-1" wire:key="insight-pages-{{ $insight->id }}">
                    <summary class="text-muted-foreground w-fit cursor-pointer hover:text-foreground">{{ trans_choice('lynx-insights::admin.more_pages', count($rest), ['count' => \LynxCms\Core\Support\LocalizedNumber::format(count($rest))]) }}</summary>
                    <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                        @foreach ($rest as $link)
                            <a href="{{ route('lynx.admin.pages.edit', $link['id']) }}" class="text-primary underline-offset-4 hover:underline" data-insight-page="{{ $link['id'] }}">{{ $link['title'] }}</a>
                        @endforeach
                    </p>
                </details>
            @endif
        </div>
    @endif

    @if ($insight->state === \LynxCms\Insights\InsightState::Running)
        @php($done = count($steps) - count(\LynxCms\Insights\PlanSteps::pending($steps)))
        <div class="mb-3 grid gap-2 text-sm" role="status" data-insight-progress="{{ $done }}/{{ count($steps) }}">
            <p>{{ __('lynx-insights::admin.progress', ['done' => \LynxCms\Core\Support\LocalizedNumber::format($done), 'total' => \LynxCms\Core\Support\LocalizedNumber::format(count($steps))]) }}</p>
            <div class="bg-muted h-2 overflow-hidden rounded-full" role="progressbar" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $done }}" aria-label="{{ __('lynx-insights::admin.progress_label') }}">
                <div class="bg-primary h-full rounded-full transition-all" style="width: {{ count($steps) > 0 ? round($done / count($steps) * 100) : 0 }}%"></div>
            </div>
        </div>
        @include('lynx-insights::admin.steps', ['steps' => $steps, 'insight' => $insight, 'links' => $links])
    @elseif ($isOpen && $failed > 0)
        <x-lynx-admin::ui.alert variant="destructive" class="mb-3" data-insight-failed>
            <x-lynx-admin::ui.alert-title>{{ trans_choice('lynx-insights::admin.failed_steps', $failed, ['count' => $failed, 'done' => \LynxCms\Insights\PlanSteps::count($steps, \LynxCms\Insights\PlanSteps::DONE)]) }}</x-lynx-admin::ui.alert-title>
        </x-lynx-admin::ui.alert>
        @include('lynx-insights::admin.steps', ['steps' => $steps, 'insight' => $insight, 'links' => $links])
    @endif

    @if ($isOpen && $reviewing === $insight->id)
        <div class="bg-muted mb-4 rounded-lg p-4 text-sm" role="status" data-insight-review>
            <p class="mb-2 font-medium">{{ __('lynx-insights::admin.review.title') }}</p>
            <ul class="grid list-disc gap-1 ps-5">
                @foreach ($preview as $summary)
                    <li>{{ $summary }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isOpen && $canReview)
        <div class="flex flex-wrap gap-3">
            @if ($insight->plan !== [])
                @if ($reviewing === $insight->id)
                    <x-lynx-admin::ui.button type="button" wire:click="approve({{ $insight->id }})" data-insight-approve>{{ __('lynx-insights::admin.approve') }}</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="closeReview" data-insight-close-review>{{ __('lynx-insights::admin.review.close') }}</x-lynx-admin::ui.button>
                @else
                    <x-lynx-admin::ui.button type="button" wire:click="review({{ $insight->id }})" data-insight-review-button>{{ __('lynx-insights::admin.review.open') }}</x-lynx-admin::ui.button>
                @endif
            @endif
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="archive({{ $insight->id }})" data-insight-archive>{{ __('lynx-insights::admin.archive') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="ghost" type="button" wire:click="dismiss({{ $insight->id }})" data-insight-dismiss>{{ __('lynx-insights::admin.dismiss') }}</x-lynx-admin::ui.button>
        </div>
    @endif
</section>
