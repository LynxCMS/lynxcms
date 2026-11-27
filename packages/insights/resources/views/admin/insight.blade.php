{{-- One insight on the Insights screen: its wording, evidence and pages, and, while open, the review and the decisions. --}}
@php($isOpen = $insight->state === \LynxCms\Insights\InsightState::Open)
<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="insight-{{ $insight->id }}" wire:key="insight-{{ $insight->id }}" data-insight="{{ $insight->id }}" data-insight-state="{{ $insight->state->value }}">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="insight-{{ $insight->id }}" class="text-base font-semibold">{{ $insight->title }}</h2>
        @unless ($isOpen)
            <x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-insights::admin.state.'.$insight->state->value) }}</x-lynx-admin::ui.badge>
        @endunless
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

    @if ($links !== [])
        <p class="mb-4 flex flex-wrap gap-x-3 gap-y-1 text-sm" data-insight-pages>
            @foreach ($links as $link)
                <a href="{{ route('lynx.admin.pages.edit', $link['id']) }}" class="text-primary underline-offset-4 hover:underline" data-insight-page="{{ $link['id'] }}">{{ $link['title'] }}</a>
            @endforeach
        </p>
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
