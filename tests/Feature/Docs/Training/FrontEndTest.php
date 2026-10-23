<?php

declare(strict_types=1);

/*
 * Chapter 3 of the training site guide: the front end built from BlatUI's Bloom template.
 */

require_once __DIR__.'/training.php';

beforeEach(function (): void {
    trainingSite();
    $this->withoutVite();
});

it('renders the home page sections from the home template\'s fields', function (): void {
    $this->get('/')->assertOk()
        ->assertSeeInOrder(['Learn to teach wellness', '2,400+', 'Certified', 'Find your course', 'Yoga teacher training', 'How booking works', 'What graduates say', 'Questions'])
        ->assertSee('hreflang="pt-PT"', false)
        ->assertSee('Main navigation');
});

it('renders the same page in Portuguese, with Portuguese URLs and texts', function (): void {
    $this->get('/pt')->assertOk()
        ->assertSee('Aprenda a ensinar bem-estar')
        ->assertSee('Ver todos os cursos')
        ->assertSee('/pt/cursos/formacao-professores-yoga', false)
        ->assertSee('lang="pt-PT"', false);
});

it('filters the catalogue by level and search, and ignores what it does not know', function (): void {
    $this->get('/courses?level=beginner')->assertOk()
        ->assertSee('Mindful movement')->assertSee('Breathwork foundations')->assertDontSee('Sports nutrition')->assertSee('2 courses');

    $this->get('/courses?q=nutrition')->assertOk()->assertSee('Sports nutrition')->assertSee('1 course');
    $this->get('/courses?level=nonsense&q="%2C|')->assertOk()->assertSee('6 courses');
    $this->get('/pt/cursos?level=advanced')->assertOk()->assertSee('Formação de professores de yoga')->assertSee('2 cursos');
});

it('shows a course with its curriculum, instructors and next dates', function (): void {
    $this->get('/courses/yoga-teacher-training')->assertOk()
        ->assertSee('Anatomy and alignment')->assertSee('Ana Ribeiro')->assertSee('Lisbon studio')->assertSee('Upcoming dates');
});

it('shows a session with its place, price and seats', function (): void {
    $session = lynx()->pages->findOne('template=session, sort=starts');

    $this->get($session->url)->assertOk()->assertSee('Lisbon studio')->assertSee('€2,400.00')->assertSeeInOrder(['16', 'seats left']);
});

it('lists instructors, studios and news, and renders their pages', function (string $path, string $text): void {
    $this->get($path)->assertOk()->assertSee($text);
})->with([
    ['/instructors', 'Tomás Ferreira'],
    ['/locations/porto', 'Rua de Cedofeita 300'],
    ['/posts', 'Our new Porto studio'],
    ['/about', 'since 2011'],
]);
