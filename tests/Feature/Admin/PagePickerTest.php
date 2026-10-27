<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PagePicker;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $this->actingAs(new GenericUser(['id' => 1]));
});

function pid(string $path): int
{
    return (int) lynx()->pages->get($path)->id;
}

it('shows the chosen pages by title', function (): void {
    Livewire::test(PagePicker::class, ['value' => [pid('/courses/alpha/')]])
        ->assertSee('Alpha')
        ->assertSeeHtml('wire:click="remove('.pid('/courses/alpha/').')"');
});

it('finds pages by title, leaving out chosen ones', function (): void {
    Livewire::test(PagePicker::class, ['value' => [pid('/courses/alpha/')]])
        ->set('search', 'a')
        ->assertSeeHtml('wire:click="add('.pid('/courses/beta/').')"')
        ->assertDontSeeHtml('wire:click="add('.pid('/courses/alpha/').')"');
});

it('finds pages by the text of their searchable fields, after title matches', function (): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    $beta = lynx()->pages->get('/courses/beta/');
    $beta->summary = 'All about alphabets';
    $beta->save();

    Livewire::test(PagePicker::class, ['value' => []])
        ->set('search', 'alpha')
        ->assertSeeHtmlInOrder(['wire:click="add('.pid('/courses/alpha/').')"', 'wire:click="add('.pid('/courses/beta/').')"']);
});

it('adds and removes pages', function (): void {
    Livewire::test(PagePicker::class, ['value' => []])
        ->call('add', pid('/courses/alpha/'))
        ->call('add', pid('/courses/beta/'))
        ->assertSet('value', [pid('/courses/alpha/'), pid('/courses/beta/')])
        ->call('remove', pid('/courses/alpha/'))
        ->assertSet('value', [pid('/courses/beta/')]);
});

it('replaces the choice when the field takes one page', function (): void {
    Livewire::test(PagePicker::class, ['value' => [pid('/courses/alpha/')], 'multiple' => false])
        ->call('add', pid('/courses/beta/'))
        ->assertSet('value', [pid('/courses/beta/')]);
});

it('only offers and accepts the allowed templates', function (): void {
    Livewire::test(PagePicker::class, ['value' => [], 'templates' => ['courses']])
        ->set('search', 'e')
        ->assertSeeHtml('wire:click="add('.pid('/courses/').')"')
        ->assertDontSeeHtml('wire:click="add('.pid('/courses/beta/').')"')
        ->call('add', pid('/courses/beta/'))
        ->assertSet('value', []);
});

it('ignores pages that do not exist', function (): void {
    Livewire::test(PagePicker::class, ['value' => []])
        ->call('add', 9999)
        ->assertSet('value', []);
});

it('keeps the search box first and the chosen pages below it, with results floating over them', function (): void {
    Livewire::test(PagePicker::class, ['value' => [pid('/courses/alpha/')]])
        ->set('search', 'Bet')
        ->assertSeeHtmlInOrder(['aria-label="Search pages"', 'aria-label="Matching pages"', 'aria-label="Chosen pages"'])
        ->assertSeeHtml('data-picker-results');
});
