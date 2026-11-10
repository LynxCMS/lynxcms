{{-- The row of stats at the top of the home screen (HomeScreen::stat()): a number, what it counts, and a link when there is one. --}}
@props(['stats'])
@if ($stats !== [])
    <section aria-label="{{ __('lynx-admin::home.stats.label') }}" {{ $attributes }}>
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]">
            @foreach ($stats as $stat)
                @php($tag = $stat['url'] === null ? 'div' : 'a')
                <li class="min-w-0" data-home-stat="{{ $stat['key'] }}">
                    <{{ $tag }} @if ($stat['url'] !== null) href="{{ $stat['url'] }}" @endif
                        @class(['bg-card text-card-foreground focus-visible:ring-ring/50 grid h-full min-w-0 gap-1 rounded-xl border p-4 outline-none focus-visible:ring-[3px]', 'hover:bg-muted/60' => $stat['url'] !== null])>
                        <span class="text-muted-foreground flex min-w-0 items-center gap-2 text-sm">
                            @if ($stat['icon'] !== null)
                                <x-lynx-admin::ui.icon :name="$stat['icon']" class="text-primary dark:text-[color-mix(in_oklab,var(--primary),var(--foreground)_30%)] size-4 shrink-0" aria-hidden="true" />
                            @endif
                            <span class="truncate" title="{{ $stat['label'] }}">{{ $stat['label'] }}</span>
                        </span>
                        <span class="text-2xl font-semibold tabular-nums">{{ is_int($stat['value']) ? \Illuminate\Support\Number::format($stat['value'], locale: app()->getLocale()) : $stat['value'] }}</span>
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>
    </section>
@endif
