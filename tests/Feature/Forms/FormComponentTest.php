<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    RateLimiter::clear('lynx-forms');
});

function quoteComponent(string $language = 'pt'): Testable
{
    return lynx()->language($language, fn (): Testable => Livewire::test(FormComponent::class, ['form' => 'quote', 'pageId' => (int) lynx()->pages->get('/courses/')?->id]));
}

/** Fills both steps with valid answers. */
function fillQuote(Testable $component): Testable
{
    return $component->set('data.type', 'film')
        ->call('addRow', 'deliverables')->set('data.deliverables.0.format', 'Teaser')
        ->call('next')
        ->set('data.name', 'Ana Lima')->set('data.email', 'ana@example.com')->set('data.consent', true);
}

it('shows the first step in the visitor\'s language', function (): void {
    quoteComponent()->assertSee('Tipo de projeto')->assertSee('Passo 1 de 2')->assertDontSee('Nome')
        ->assertSee('Seguinte')->assertSee('Escolha…');
});

it('sends a field to the server when it is left, so checks and live extras follow', function (): void {
    // Livewire 4: .blur alone only updates the browser's copy; .live.blur also sends it.
    fillQuote(quoteComponent())->assertSeeHtml('wire:model.live.blur="data.name"')->assertDontSeeHtml('wire:model.blur=');
});

it('checks the current step before moving on', function (): void {
    quoteComponent()->call('next')->assertHasErrors('data.type')->assertSet('step', 0)
        ->set('data.type', 'film')->call('next')->assertHasNoErrors()->assertSet('step', 1)->assertSee('Nome')
        ->call('back')->assertSet('step', 0);
});

it('shows fields as the answers ask for them', function (): void {
    quoteComponent()->assertDontSee('Câmaras')->set('data.type', 'live')->assertSee('Câmaras');
});

it('checks a field as soon as it is left', function (): void {
    quoteComponent()->call('next')->set('data.type', 'film')->assertHasNoErrors('data.type');
});

it('adds and removes group rows', function (): void {
    $component = quoteComponent()->call('addRow', 'deliverables')->call('addRow', 'deliverables');
    expect($component->get('data.deliverables'))->toHaveCount(2);

    $component->call('removeRow', 'deliverables', 0);
    expect($component->get('data.deliverables'))->toHaveCount(1);
});

it('sends the answers with files and thanks the visitor in their language', function (): void {
    $component = fillQuote(quoteComponent())->set('files.brief', [UploadedFile::fake()->create('brief.pdf', 50, 'application/pdf')]);
    $this->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors()->assertSet('isSent', true)->assertSee('Obrigado, respondemos em breve.');

    $submission = Submission::query()->sole();
    expect($submission->data['deliverables'])->toBe([['format' => 'Teaser']])
        ->and($submission->files)->toHaveCount(1)
        ->and($submission->language()?->code)->toBe('pt')
        ->and($submission->page_id)->toBe(lynx()->pages->get('/courses/')?->id);
});

it('goes back to the step with an error when sending fails validation', function (): void {
    $component = fillQuote(quoteComponent())->set('data.type', 'opera');
    $this->travel(5)->seconds();

    $component->call('submit')->assertHasErrors('data.type')->assertSet('step', 0)->assertSet('isSent', false);
});

it('pretends to accept spam and keeps nothing', function (): void {
    $component = fillQuote(quoteComponent());
    $field = array_key_first($component->get('extraFields')->data);
    $component->set('extraFields.data.'.$field, 'I am a bot');
    $this->travel(5)->seconds();

    $component->call('submit')->assertSet('isSent', true);
    expect(Submission::query()->count())->toBe(0);
});

it('pretends to accept forms sent faster than a person could', function (): void {
    // Frozen: the honeypot's timing check counts whole seconds, so a run crossing a second
    // boundary between filling and sending would pass it.
    $this->freezeTime();
    fillQuote(quoteComponent())->call('submit')->assertSet('isSent', true);

    expect(Submission::query()->count())->toBe(0);
});

it('slows down repeated sending', function (): void {
    config(['lynx-forms.rate_limit.attempts' => 1]);
    $first = fillQuote(quoteComponent());
    $this->travel(5)->seconds();
    $first->call('submit')->assertSet('isSent', true);

    $second = fillQuote(quoteComponent());
    $this->travel(5)->seconds();
    $second->call('submit')->assertSet('isSent', false)->assertSee('Demasiadas tentativas');
    expect(Submission::query()->count())->toBe(1);
});

it('says when sending fails for another reason', function (): void {
    lynx()->hook(SubmitForm::class)->before(fn () => throw new RuntimeException('Disk full'));
    $component = fillQuote(quoteComponent());
    $this->travel(5)->seconds();

    $component->call('submit')->assertSet('isSent', false)->assertSee('Your message could not be sent');
});

it('sums up the earlier answers on the last step', function (): void {
    quoteComponent()->set('data.type', 'film')
        ->call('addRow', 'deliverables')->set('data.deliverables.0.format', 'Teaser')
        ->call('next')
        ->assertSee('Reveja as respostas antes de enviar.')->assertSee('Filme')->assertSee('Teaser');
});
