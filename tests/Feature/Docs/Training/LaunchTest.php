<?php

declare(strict_types=1);

use App\Bookings\Seats;
use Database\Seeders\AtlasCacheSeeder;
use Illuminate\Support\Facades\File;
use LynxCms\Cache\PageCacheEntry;

/*
 * Chapter 10 of the training site guide: the page cache keeps public pages, islands keep forms
 * and seats fresh, and a booking clears the pages that show its session.
 */

require_once __DIR__.'/training.php';

beforeEach(function (): void {
    config(['lynx-cache.pages.enabled' => true, 'lynx-cache.pages.path' => testingPath('atlas-cache')]);
    File::deleteDirectory(testingPath('atlas-cache'));
    trainingSite();
    new AtlasCacheSeeder()->run();
    $this->withoutVite();
});

function trainingIsStored(string $path): bool
{
    return PageCacheEntry::query()->where('page_id', lynx()->pages->get($path)?->id)->exists();
}

it('keeps public pages for guests, and never members\' materials', function (): void {
    foreach (['/', '/courses', '/courses/sports-nutrition', '/posts'] as $url) {
        $this->get($url)->assertOk();
    }

    expect(trainingIsStored('/'))->toBeTrue()
        ->and(trainingIsStored('/courses/sports-nutrition/'))->toBeTrue()
        ->and(trainingIsStored('/posts/'))->toBeTrue();

    $this->get('/courses/sports-nutrition/materials')->assertRedirect(route('login'));
    expect(trainingIsStored('/courses/sports-nutrition/materials/'))->toBeFalse();
});

it('stores forms and seats as islands, so every visitor gets them fresh', function (): void {
    $html = $this->get('/courses/sports-nutrition')->assertOk()->getContent();
    $session = lynx()->pages->get('/courses/sports-nutrition/')->children('template=session')->first();
    $sessionHtml = $this->get($session->url)->assertOk()->getContent();

    expect($html)->toContain('<lynx-island data-island=')->not->toContain('Your question')
        ->and($sessionHtml)->toContain('<lynx-island data-island=')->not->toContain('seats left');
});

it('clears the session, its course, the catalogue and the home page when a seat is taken', function (): void {
    $session = lynx()->pages->get('/courses/sports-nutrition/')->children('template=session')->first();
    foreach (['/', '/courses', '/courses/sports-nutrition', $session->url] as $url) {
        $this->get($url);
    }

    Seats::take($session->id);

    expect(trainingIsStored('/courses/sports-nutrition/'))->toBeFalse()
        ->and(trainingIsStored('/courses/'))->toBeFalse()
        ->and(trainingIsStored('/'))->toBeFalse()
        ->and(trainingIsStored('/posts/'))->toBeFalse();
});
