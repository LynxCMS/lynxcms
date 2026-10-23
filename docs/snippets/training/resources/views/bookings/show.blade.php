{{-- Where Stripe sends the buyer back. The webhook may arrive a moment later, so a pending booking
     refreshes itself. --}}
<x-layouts.site :page="$page" :seo="['robots' => 'noindex, nofollow']">
    <section class="px-4 py-16">
        <div class="atlas-clay mx-auto max-w-xl p-8 text-center">
            @if ($booking->isPaid())
                <h1 class="atlas-section-title">{{ __('Thank you, your place is confirmed.') }}</h1>
                <p class="atlas-lead mt-3">@field($page->title) · @field($page->location?->title)</p>
            @else
                <meta http-equiv="refresh" content="3">
                <h1 class="atlas-section-title">{{ __('We are confirming your payment. This page updates in a moment.') }}</h1>
            @endif
        </div>
    </section>
</x-layouts.site>
