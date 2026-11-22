{{-- The weather panel's content (WeatherPanel), built from the home screen's own section. --}}
@php
    // Open-Meteo's weather codes (WMO), by the first code of each group, with a Lucide icon.
    $kinds = [95 => ['Thunderstorms', 'cloud-lightning'], 80 => ['Showers', 'cloud-rain'], 71 => ['Snow', 'cloud-snow'],
        61 => ['Rain', 'cloud-rain'], 51 => ['Drizzle', 'cloud-drizzle'], 45 => ['Fog', 'cloud-fog'],
        1 => ['Some clouds', 'cloud-sun'], 0 => ['Clear', 'sun']];
    $kind = fn (int $code): array => collect($kinds)->first(fn (array $kind, int $from): bool => $code >= $from);
@endphp
<x-lynx-admin::home.section id="home-weather" data-home-panel="weather" icon="cloud-sun" title="Weather"
    :is-empty="$days === null" empty-icon="cloud-off"
    empty-title="The forecast is not available." empty-help="Open-Meteo did not answer. The panel tries again on the next visit.">
    @foreach ($days ?? [] as $day)
        <li class="flex items-center gap-3 border-b py-2.5 text-sm" data-weather-day="{{ $day['date'] }}">
            <x-lynx-admin::ui.icon :name="$kind($day['code'])[1]" class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
            <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($day['date'])->isoFormat('dddd') }}</span>
            <span class="text-muted-foreground">{{ $kind($day['code'])[0] }}</span>
            <span class="ms-auto tabular-nums">{{ round($day['max']) }}° <span class="text-muted-foreground">{{ round($day['min']) }}°</span></span>
        </li>
    @endforeach
</x-lynx-admin::home.section>
