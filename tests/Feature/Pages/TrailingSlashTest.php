<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'risk';
    $page->title = 'Risk';
    $page->price = 10;
    $page->setLanguageName('pt', 'risco');
    $page->save();
    $this->page = lynx()->pages->get('/courses/risk/');
    lynx()->run(SavePage::class, ['id' => lynx()->pages->get('/')?->id, 'names' => ['pt' => ['active' => true]]]);
});

it('leaves the slash off page URLs by default, as Laravel apps write URLs', function (): void {
    $home = lynx()->pages->get('/');

    expect($this->page->localUrl('pt'))->toBe('/pt/cursos/risco')
        ->and($this->page->localUrl('default'))->toBe('/courses/risk')
        ->and($home instanceof Page ? [$home->localUrl('default'), $home->localUrl('pt')] : null)->toBe(['/', '/pt']);
});

it('ends page URLs with a slash when trailing_slash is true, as ProcessWire writes them', function (): void {
    config(['lynx.trailing_slash' => true]);

    expect($this->page->localUrl('pt'))->toBe('/pt/cursos/risco/')
        ->and(lynx()->pages->get('/')?->localUrl('pt'))->toBe('/pt/');
});

it('opens a page at both forms of its address', function (): void {
    $this->get('/pt/cursos/risco')->assertOk()->assertSee('Risk');
    $this->get('/pt/cursos/risco/')->assertOk()->assertSee('Risk');
});

it('redirects the default language\'s prefix to the URL in the configured form', function (): void {
    $this->get('/default/courses/risk')->assertRedirect('http://localhost/courses/risk');

    config(['lynx.trailing_slash' => true]);
    $this->get('/default/courses/risk')->assertRedirect('http://localhost/courses/risk/');
});
