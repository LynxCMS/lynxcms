<?php

declare(strict_types=1);

use Database\Seeders\AtlasSeoSeeder;
use LynxCms\Core\Pages\Page;

/*
 * Chapter 5 of the training site guide: titles, structured data and the sitemap.
 */

require_once __DIR__.'/training.php';
require_once TRAINING.'/database/seeders/AtlasSeoSeeder.php';

beforeEach(function (): void {
    config(['lynx-seo.block_non_production' => false]);
    trainingSite();
    new AtlasSeoSeeder()->run();
    $this->withoutVite();
});

/** @return array<string, mixed>|null the page's JSON-LD node of a type, in a language */
function trainingNode(string $path, string $type, string $language = 'en'): ?array
{
    $page = lynx()->pages->get($path);
    assert($page instanceof Page);

    return collect(lynx()->language($language, fn (): array => lynx()->seo($page)->jsonLd['@graph']))->firstWhere('@type', $type);
}

it('titles pages with the site name, in each language', function (): void {
    $this->get('/courses/sports-nutrition')->assertSee('<title>Sports nutrition | Atlas Training</title>', false);
    $this->get('/pt/cursos/nutricao-desportiva')->assertSee('<title>Nutrição desportiva | Atlas Training</title>', false);
});

it('describes a course as a Course with its price, offered by the school', function (): void {
    $course = trainingNode('/courses/sports-nutrition/', 'Course');

    expect($course['name'])->toBe('Sports nutrition')
        ->and($course['description'])->toStartWith('Plan meals')
        ->and($course['offers']['price'])->toEqual(420)
        ->and($course['offers']['priceCurrency'])->toBe('EUR')
        ->and($course['provider'])->toHaveKey('@id');
});

it('describes a session as an Event at a Place', function (): void {
    $session = lynx()->pages->findOne('template=session, sort=starts');
    $event = trainingNode('/'.$session->path().'/', 'Event', 'pt');

    expect($event['name'])->toStartWith('Formação de professores de yoga, ')
        ->and($event['eventStatus'])->toBe('https://schema.org/EventScheduled')
        ->and($event['startDate'])->toBe($session->starts->toIso8601String())
        ->and(trainingNode('/locations/lisbon/', 'Place')['address']['postalCode'] ?? null)->toBe('1100-420');
});

it('marks the home page\'s questions as an FAQPage', function (): void {
    $faq = trainingNode('/', 'FAQPage');

    expect($faq['mainEntity'][0]['name'])->toBe('Do I need experience?')
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])->toStartWith('Beginner courses need none.');
});

it('keeps filtered catalogue pages out of search results', function (): void {
    $this->get('/courses')->assertDontSee('noindex', false);
    $this->get('/courses?level=beginner')->assertSee('<meta name="robots" content="noindex, follow">', false)->assertSee('rel="canonical" href="http://localhost/courses"', false);
});

it('lists every course and session in the sitemap, in both languages', function (): void {
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('/courses/sports-nutrition')
        ->assertSee('/pt/cursos/nutricao-desportiva')
        ->assertSee('hreflang="pt-PT"', false);
});
