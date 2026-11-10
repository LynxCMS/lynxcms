{{--
    One list on the home screen: a heading (with an icon, linking to the full list when there is
    one) and how many there are, the list as ruled rows
    (home.row), and a "See all" link when the list shows only some of them. Modules build their
    home panels (HomeScreen) from it too.
--}}
@props([
    'id',
    'title',
    'icon' => null,
    'href' => null,
    'count' => null,
    'seeAllUrl' => null,
    'seeAllLabel' => null,
    'isEmpty' => false,
    'emptyIcon' => 'inbox',
    'emptyTitle' => '',
    'emptyHelp' => '',
])
<section aria-labelledby="{{ $id }}" {{ $attributes->class('grid content-start gap-3') }}>
    <div class="flex items-baseline justify-between gap-3">
        <h2 id="{{ $id }}" class="flex items-center gap-2 text-base font-semibold">
            @if ($icon !== null)
                <x-lynx-admin::ui.icon :name="$icon" class="text-primary dark:text-[color-mix(in_oklab,var(--primary),var(--foreground)_30%)] size-4 shrink-0" aria-hidden="true" />
            @endif
            @if ($href !== null)
                <a href="{{ $href }}" class="hover:underline focus-visible:ring-ring/50 rounded-sm underline-offset-4 outline-none focus-visible:ring-[3px]" data-home-heading-link>{{ $title }}</a>
            @else
                <span>{{ $title }}</span>
            @endif
            @if ($count !== null && $count > 0)
                <span class="text-muted-foreground font-normal tabular-nums" data-home-count>{{ $count }}</span>
            @endif
        </h2>
        @if ($seeAllUrl !== null)
            <a href="{{ $seeAllUrl }}" class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 rounded-sm text-sm underline underline-offset-4 outline-none focus-visible:ring-[3px]" data-home-see-all>{{ $seeAllLabel }}</a>
        @endif
    </div>
    @if ($isEmpty)
        <x-lynx-admin::empty-state compact :icon="$emptyIcon" :title="$emptyTitle" :help="$emptyHelp" data-home-empty />
    @else
        <ul class="border-t">{{ $slot }}</ul>
    @endif
</section>
