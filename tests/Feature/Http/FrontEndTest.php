<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;

beforeEach(function (): void {
    courseSite();
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'delivery-8';
    $page->title = 'Navigating Project Delivery';
    $page->body = '<p>English body</p>';
    $page->setLanguageName('pt', 'navegando-entrega-8');
    $page->setLanguageValue('pt', 'title', 'Navegando pela Entrega de Projetos');
    $page->save();
});

it('renders a page in the default language', function (): void {
    $this->get('/courses/delivery-8')
        ->assertOk()
        ->assertSee('Navigating Project Delivery')
        ->assertSee('<p>English body</p>', false)
        ->assertSee('<html lang="en">', false);
});

it('renders the translated URL with field fallback and lang attributes', function (): void {
    $this->get('/pt/cursos/navegando-entrega-8')
        ->assertOk()
        ->assertSee('Navegando pela Entrega de Projetos')
        ->assertSee('<div lang="en"><p>English body</p></div>', false)
        ->assertSee('<html lang="pt-PT">', false)
        ->assertSee('hreflang="pt-PT" href="http://localhost/pt/cursos/navegando-entrega-8"', false)
        ->assertSee('hreflang="x-default" href="http://localhost/courses/delivery-8"', false);
});

it('redirects old paths to the current one in the same language', function (): void {
    $page = lynx()->pages->get('/courses/delivery-8');
    $page->setLanguageName('pt', 'guia-entrega');
    $page->save();

    $this->get('/pt/cursos/navegando-entrega-8?ref=1')->assertRedirect('http://localhost/pt/cursos/guia-entrega?ref=1')->assertStatus(301);
});

it('answers 404 for unknown paths and languages a page is not published in', function (): void {
    lynx()->languages->add('es', 'Español');

    $this->get('/courses/nope')->assertNotFound();
    $this->get('/es/courses/delivery-8')->assertNotFound();
});

it('serves a newly added language without a deploy once a page has a name in it', function (): void {
    lynx()->languages->add('es', 'Español', fallback: 'default');
    $page = lynx()->pages->get('/courses/delivery-8');
    $page->setLanguageName('es', 'navegando-entrega');
    $page->save();

    // The courses page has no Spanish name yet, so its default-language name is used in the path.
    $this->get('/es/courses/navegando-entrega')->assertOk()->assertSee('Navigating Project Delivery');
});

it('remembers the language for requests without a prefix', function (): void {
    $this->get('/pt/cursos/navegando-entrega-8')->assertOk()->assertSessionHas('lynx.language', 'pt');
});

it('redirects the default language code away when it has no prefix', function (): void {
    $this->get('/default/courses/delivery-8')->assertRedirect('http://localhost/courses/delivery-8');
});

it('never serves pages on templates without URLs', function (): void {
    lynx()->fields->add('footer_text', 'text', translatable: true);
    lynx()->templates->add('settings', fields: ['footer_text'], hasUrl: false, singleton: true);
    $settings = lynx()->pages->new('settings', '/');
    $settings->name = 'settings';
    $settings->save();

    $this->get('/settings')->assertNotFound();
});

it('serves pages to users that are not Eloquent models', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'lynx_language_id' => null]))
        ->get('/courses')
        ->assertOk();
});

it('redirects a path written in another language to the page in the URL language', function (): void {
    $this->get('/pt/courses/delivery-8')->assertRedirect('http://localhost/pt/cursos/navegando-entrega-8')->assertStatus(301);
    $this->get('/cursos/navegando-entrega-8')->assertRedirect('http://localhost/courses/delivery-8');
    $this->get('/pt/cursos/nothing-here')->assertNotFound();
});
