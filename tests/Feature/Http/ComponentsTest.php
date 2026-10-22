<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Workbench\App\Livewire\EnrolForm;
use Workbench\App\Models\Enrolment;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('enrol', 'component', settings: ['allowed' => ['enrol-form']]);
    lynx()->templates->update('course', ['add' => ['enrol']]);

    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'delivery-8';
    $page->title = 'Delivery';
    $page->setLanguageName('pt', 'delivery-8-pt');
    $page->setLanguageValue('pt', 'title', 'Guia de Entrega');
    $page->enrol = ['component' => 'enrol-form', 'settings' => ['capacity' => 2]];
    $page->save();
});

it('validates component values against the registry and its settings rules', function (): void {
    $page = lynx()->pages->get('/courses/delivery-8/');
    $page->enrol = ['component' => 'unknown'];

    expect(fn () => $page->save())->toThrow(ValidationException::class);

    $page->enrol = ['component' => 'enrol-form', 'settings' => ['capacity' => 5000]];
    expect(fn () => $page->save())->toThrow(ValidationException::class);
});

it('renders the chosen app component inside a CMS page', function (): void {
    $this->withoutVite();

    $this->get('/pt/cursos/delivery-8-pt/')
        ->assertOk()
        ->assertSee('Guia de Entrega')
        ->assertSee('Inscrever-me')
        ->assertSee('wire:submit', false);
});

it('keeps visitor-facing text in translation files, not in shared settings', function (): void {
    $this->withoutVite();

    $this->get('/courses/delivery-8/')->assertSee('Enrol');
    $this->get('/pt/cursos/delivery-8-pt/')->assertSee('Inscrever-me')->assertDontSee('>Enrol<', false);
});

it('closes enrolment when the capacity setting is reached', function (): void {
    $page = lynx()->pages->get('/courses/delivery-8/');
    Enrolment::query()->insert([
        ['page_id' => $page->id, 'lang' => 'default', 'email' => 'a@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['page_id' => $page->id, 'lang' => 'default', 'email' => 'b@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    Livewire::test(EnrolForm::class, ['pageId' => $page->id, 'lang' => 'pt', 'settings' => ['capacity' => 2]])
        ->assertSee('Esgotado')
        ->assertDontSeeHtml('wire:model="email"')
        ->set('email', 'late@example.com')
        ->call('save')
        ->assertSet('sent', false);

    expect(Enrolment::query()->count())->toBe(2);
});

it('keeps the page language across Livewire requests and stores data in app tables', function (): void {
    $page = lynx()->pages->get('/courses/delivery-8/');

    Livewire::test(EnrolForm::class, ['pageId' => $page->id, 'lang' => 'pt', 'settings' => []])
        ->set('email', 'ana@example.com')
        ->call('save')
        ->assertSet('sent', true)
        ->assertSee('Guia de Entrega');

    expect(Enrolment::query()->first())->lang->toBe('pt')->page_id->toBe($page->id);
});

it('does not let visitors change the page, language or settings of a component', function (string $property, mixed $value): void {
    $page = lynx()->pages->get('/courses/delivery-8/');

    expect(fn () => Livewire::test(EnrolForm::class, ['pageId' => $page->id, 'lang' => 'pt', 'settings' => ['capacity' => 2]])->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'settings' => ['settings.capacity', 999],
    'page' => ['pageId', 1],
    'language' => ['lang', 'default'],
]);
