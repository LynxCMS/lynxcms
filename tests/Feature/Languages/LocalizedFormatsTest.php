<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Number;
use LynxCms\Admin\Locale\SaveAdminLocale;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Support\LocalizedNumber;
use LynxCms\Core\Templates\Views\FieldSnippet;

/*
 * Dates and numbers people read follow the locale: dates through Carbon's locale formats (LL, LLL),
 * numbers through Laravel's Number, so a new language needs no formatting code.
 */

beforeEach(function (): void {
    courseSite();
});

it('writes date fields into new views in the locale\'s own order', function (array $settings, string $format): void {
    lynx()->fields->add('starts_on', 'date', settings: $settings);
    $field = app(FieldRegistry::class)->find('starts_on');

    expect(implode("\n", app(FieldSnippet::class)->lines('$page', $field, $settings)))->toContain("isoFormat('{$format}')");
})->with([
    'a day' => [[], 'LL'],
    'a day and a time' => [['time' => true], 'LLL'],
]);

it('shows today on the home screen in the language of the admin', function (string $locale, string $expected): void {
    $this->travelTo('2026-07-29 14:30:00');
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->actingAs(Actor::user(1), fn (): mixed => lynx()->run(SaveAdminLocale::class, ['locale' => $locale]));

    $this->get('/lynx')->assertOk()->assertSee($expected);
})->with([
    'en' => ['en', 'July 29, 2026'],
    'pt' => ['pt', '29 de julho de 2026'],
    'es' => ['es', '29 de julio de 2026'],
]);

it('formats numbers and file sizes in the current locale', function (string $locale): void {
    app()->setLocale($locale);

    expect(LocalizedNumber::format(1234567))->toBe(Number::format(1234567, locale: $locale))
        ->and(LocalizedNumber::fileSize(1_610_612_736, precision: 1))->toBe(Number::withLocale($locale, fn (): string => Number::fileSize(1_610_612_736, precision: 1)))
        ->and(LocalizedNumber::fileSize(1_610_612_736, precision: 1))->toBe(str_replace('.', $locale === 'en' ? '.' : ',', '1.5 GB'));
})->with(['en', 'pt-PT', 'de']);

it('formats numbers in a locale ICU does not know', function (): void {
    app()->setLocale('jp');

    expect(LocalizedNumber::format(1234567))->toBe(Number::format(1234567))
        ->and(LocalizedNumber::fileSize(2048))->toBe(Number::fileSize(2048));
});

it('leaves the locale of Number as it was', function (): void {
    app()->setLocale('de');
    LocalizedNumber::fileSize(2048);

    expect(Number::defaultLocale())->toBe('en');
});
