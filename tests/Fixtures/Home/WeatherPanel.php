<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Home;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

/**
 * The weather for the next days from Open-Meteo, on the admin's home screen: the docs' example of
 * a home panel with data from another service (docs/admin/extending.md). It loads after the page
 * (lazy), caches the forecast and shows a notice when the service does not answer.
 */
final class WeatherPanel extends Component
{
    public const string CACHE_KEY = 'home.weather';

    private const string ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    private const int CACHE_MINUTES = 30;

    private const int TIMEOUT_SECONDS = 3;

    private const int DAYS = 3;

    public function placeholder(): View
    {
        return view('livewire.weather-panel-placeholder');
    }

    public function render(): View
    {
        return view('livewire.weather-panel', ['days' => $this->forecast()]);
    }

    /** @return list<array{date: string, code: int, max: float, min: float}>|null null when Open-Meteo did not answer */
    private function forecast(): ?array
    {
        $days = Cache::get(self::CACHE_KEY);

        if (is_array($days)) {
            return $days;
        }

        $days = $this->fetch();

        if ($days !== null) {
            Cache::put(self::CACHE_KEY, $days, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $days;
    }

    /** @return list<array{date: string, code: int, max: float, min: float}>|null */
    private function fetch(): ?array
    {
        try {
            $daily = Http::timeout(self::TIMEOUT_SECONDS)->get(self::ENDPOINT, [
                'latitude' => config('services.open_meteo.latitude'),
                'longitude' => config('services.open_meteo.longitude'),
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone' => 'auto',
                'forecast_days' => self::DAYS,
            ])->throw()->json('daily');
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return null;
        }

        return array_map(fn (int $day): array => [
            'date' => $daily['time'][$day],
            'code' => (int) $daily['weather_code'][$day],
            'max' => (float) $daily['temperature_2m_max'][$day],
            'min' => (float) $daily['temperature_2m_min'][$day],
        ], array_keys($daily['time']));
    }
}
