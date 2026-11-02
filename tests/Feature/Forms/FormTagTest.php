<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\FormSchema;

/*
 * <x-lynx-form> renders a form in place, or with lazy a placeholder that loads the form once it
 * scrolls into view. A lazy form mounts in a later request without the page's language prefix,
 * so the tag hands it the page's language.
 */

beforeEach(function (): void {
    courseSite();
    $form = lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => ['default' => 'Contact us']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $form->id, 'values' => [FormSchema::FIELDS => [['type' => 'text', 'values' => ['form_label' => ['default' => 'Your name', 'pt' => 'O seu nome']]]]]]);
    lynx()->pages->setStatus($form->id, remove: ['unpublished']);
});

/**
 * The parameters a lazy component mounts with, read from its placeholder.
 *
 * @return array<string, mixed>
 */
function lazyMountOf(string $html): array
{
    preg_match("/__lazyLoad\\('([^']+)'/", html_entity_decode($html), $match);

    return Value::map(data_get(json_decode(base64_decode($match[1] ?? '', true) ?: '', true), 'data.forMount.0'));
}

it('renders the form in place by default', function (): void {
    expect(Blade::render('<x-lynx-form form="contact" />'))->toContain('lynx-form-body')
        ->not->toContain('data-lynx-form-placeholder');
});

it('renders a placeholder when lazy, and the form only when it loads', function (): void {
    $html = Blade::render('<x-lynx-form form="contact" lazy />');

    expect($html)->toContain('data-lynx-form-placeholder')
        ->not->toContain('lynx-form-body')
        ->and(lazyMountOf($html)['form'] ?? null)->toBe('contact');
});

it('gives a lazy form the language of the page it is on', function (): void {
    $html = lynx()->language('pt', fn (): string => Blade::render('<x-lynx-form form="contact" lazy />'));

    expect(lazyMountOf($html)['lang'] ?? null)->toBe('pt');
});

it('loads the form in the language it was given', function (): void {
    Livewire::test('lynx-form', ['form' => 'contact', 'lang' => 'pt'])
        ->assertSee('O seu nome')
        ->assertDontSee('Your name');
});
