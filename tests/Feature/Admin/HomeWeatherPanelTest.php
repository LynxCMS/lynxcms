<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Tests\Fixtures\Home\WeatherPanel;

/*
 * The docs' example of a home panel with data from another service (docs/admin/extending.md): the
 * forecast from Open-Meteo, loaded after the page, cached, and a notice when the service fails.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    View::addLocation(__DIR__.'/../../Fixtures/views');
    Livewire::component('weather-panel', WeatherPanel::class);
    config(['services.open_meteo' => ['latitude' => 38.72, 'longitude' => -9.14]]);
});

function openMeteoForecast(): array
{
    return ['daily' => [
        'time' => ['2026-11-17', '2026-11-18', '2026-11-19'],
        'weather_code' => [61, 0, 95],
        'temperature_2m_max' => [21.6, 19.8, 18.3],
        'temperature_2m_min' => [18.8, 16.5, 12.8],
    ]];
}

it('draws the home screen without waiting for the forecast', function (): void {
    Http::fake();
    app(HomeScreen::class)->panel('home-weather', key: 'weather', column: HomeScreen::SIDE);

    $this->get('/lynx')->assertOk()->assertSee('data-weather-loading', false);
    Http::assertNothingSent();
});

it('shows the next days from Open-Meteo', function (): void {
    Http::fake(['api.open-meteo.com/*' => Http::response(openMeteoForecast())]);

    Livewire::test(WeatherPanel::class)
        ->assertSeeHtml('data-weather-day="2026-11-17"')
        ->assertSee(['Rain', 'Clear', 'Thunderstorms', '22°', '13°']);
    Http::assertSent(fn ($request): bool => $request['latitude'] === 38.72 && $request['forecast_days'] === 3);
});

it('asks Open-Meteo once while the forecast is cached', function (): void {
    Http::fake(['api.open-meteo.com/*' => Http::response(openMeteoForecast())]);

    Livewire::test(WeatherPanel::class);
    Livewire::test(WeatherPanel::class);

    Http::assertSentCount(1);
});

it('says the forecast is not available when Open-Meteo fails, and tries again next time', function (Closure $response, string $exception): void {
    Exceptions::fake();
    Http::fake(['api.open-meteo.com/*' => $response]);

    Livewire::test(WeatherPanel::class)->assertSee('The forecast is not available.');

    Exceptions::assertReported($exception);
    expect(Cache::has(WeatherPanel::CACHE_KEY))->toBeFalse();
})->with([
    'an error' => [fn () => Http::response(status: 503), RequestException::class],
    'no answer' => [fn (): never => throw new ConnectionException('Timed out.'), ConnectionException::class],
]);
