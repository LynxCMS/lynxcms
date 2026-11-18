<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\FilmOnlyQuote;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * Hooks for components extending the form: narrowing a choice's options for the page it is on,
 * and acting on the submission once it is stored (handing back a download link, say).
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    RateLimiter::clear('lynx-forms');
});

function filmQuote(): Testable
{
    return Livewire::test(FilmOnlyQuote::class, ['form' => 'quote', 'pageId' => (int) lynx()->pages->get('/courses/')?->id]);
}

function fillFilmQuote(Testable $component, string $type = 'film'): Testable
{
    return $component->set('data.type', $type)->call('next')
        ->set('data.name', 'Ana Lima')->set('data.email', 'ana@example.com')->set('data.consent', true);
}

it('offers only the options the component allows', function (): void {
    filmQuote()->assertSee('Film')->assertDontSee('Livestream');
});

it('refuses an answer outside the allowed options', function (): void {
    $component = filmQuote()->set('data.type', 'live')->call('next')->assertHasErrors('data.type')->assertSet('step', 0);

    $this->travel(5)->seconds();
    fillFilmQuote($component, 'live')->call('submit')->assertHasErrors('data.type')->assertSet('isSent', false);

    expect(Submission::query()->count())->toBe(0);
});

it('hands the stored submission to the component', function (): void {
    $component = fillFilmQuote(filmQuote());
    $this->travel(5)->seconds();

    $component->call('submit')->assertSet('isSent', true)->assertSet('sentId', Submission::query()->value('id'));
});

it('hands nothing over when the submission was spam', function (): void {
    $component = fillFilmQuote(filmQuote());
    $field = array_key_first($component->get('extraFields')->data);
    $component->set('extraFields.data.'.$field, 'I am a bot');
    $this->travel(5)->seconds();

    $component->call('submit')->assertSet('isSent', true)->assertSet('sentId', null);
});
