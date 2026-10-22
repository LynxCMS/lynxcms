<?php

declare(strict_types=1);

it('builds a demo site that serves every language, and can run twice', function (): void {
    $this->artisan('lynx:demo')->assertSuccessful();
    $this->artisan('lynx:demo')->assertSuccessful();

    $this->get('/pt/')->assertOk()->assertSee('Início');
    $this->get('/courses/')->assertOk();
    $this->get('/courses/delivery-8/')->assertOk()->assertSee('Delivery');
    $this->get('/pt/cursos/')->assertOk();
    $this->get('/pt/cursos/navegando-entrega-8/')->assertOk()->assertSee('Navegando pela Entrega de Projetos');

    expect(lynx()->pages->count('template=course'))->toBeGreaterThan(0)
        // Names match in the current language: the last request was Portuguese.
        ->and(lynx()->pages->find('template=course, name=navegando-entrega-8')->count())->toBe(1)
        ->and(lynx()->pages->find('template=course, name=delivery-8, language=default')->count())->toBe(1);
});

it('skips generated pages that already exist when run twice', function (): void {
    $this->artisan('lynx:demo', ['--pages' => 2])->assertSuccessful();
    $this->artisan('lynx:demo', ['--pages' => 3])->assertSuccessful();

    expect(lynx()->pages->count('template=course, name^=generated-, include=all'))->toBe(3);
});
