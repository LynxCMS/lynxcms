<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;
use Workbench\App\Models\User;

/*
 * Forms that members answer once each, such as a course survey: guests are asked to sign in,
 * and a second answer from the same account is refused (ADR-0076).
 */

final class FeedbackSurvey extends Form
{
    public function key(): string
    {
        return 'feedback';
    }

    public function label(): array
    {
        return ['default' => 'Course feedback', 'pt' => 'Avaliação do curso'];
    }

    public function fields(): array
    {
        return [FormField::radio('rating')->label('Rating')->required()->options(['1' => '1', '5' => '5'])];
    }

    public function oncePerUser(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    courseSite();
    RateLimiter::clear('lynx-forms');
    lynx()->forms()->register(FeedbackSurvey::class);
    $this->member = User::query()->forceCreate(['name' => 'Mia', 'email' => 'mia@example.com', 'password' => Hash::make('long enough')]);
});

function answerFeedback(): void
{
    lynx()->run(SubmitForm::class, ['form' => 'feedback', 'data' => ['rating' => '5']]);
}

it('takes one answer from a signed-in member', function (): void {
    $this->actingAs($this->member);
    answerFeedback();

    expect(Submission::query()->fromUser($this->member->id)->where('form', 'feedback')->count())->toBe(1);
});

it('refuses a second answer from the same member', function (): void {
    $this->actingAs($this->member);
    answerFeedback();

    expect(fn () => answerFeedback())->toThrow(ValidationException::class, 'You have already answered this form.');
    expect(Submission::query()->count())->toBe(1);
});

it('refuses guests', function (): void {
    expect(fn () => answerFeedback())->toThrow(ValidationException::class, 'Sign in to answer this form.');
});

it('tells guests to sign in instead of showing the form, in their language', function (): void {
    lynx()->language('pt', fn () => Livewire::test(FormComponent::class, ['form' => 'feedback']))
        ->assertSee('Inicie sessão para responder a este formulário.')->assertDontSee('Rating');
});

it('tells members who answered that they did, instead of the form', function (): void {
    $this->actingAs($this->member);
    answerFeedback();

    Livewire::test(FormComponent::class, ['form' => 'feedback'])->assertSee('You have already answered this form.')->assertDontSee('Rating');
});

it('lets other forms take any number of answers', function (): void {
    $this->actingAs($this->member);
    lynx()->forms()->register(QuoteForm::class);

    foreach ([1, 2] as $attempt) {
        lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => ['type' => 'film', 'name' => 'Mia', 'email' => 'mia@example.com', 'consent' => true]]);
    }

    expect(Submission::query()->where('form', 'quote')->count())->toBe(2);
});

it('offers the setting in the form builder', function (): void {
    $page = lynx()->run(CreateForm::class, ['name' => 'survey', 'label' => ['default' => 'Survey']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [
        FormSchema::ONCE_PER_USER => true,
        FormSchema::FIELDS => [['type' => 'text', 'values' => ['form_label' => ['default' => 'Comments']]]],
    ]]);

    expect(lynx()->forms()->get('survey')?->oncePerUser())->toBeTrue();

    $this->actingAs($this->member);
    lynx()->run(SubmitForm::class, ['form' => 'survey', 'data' => ['comments' => 'Great']]);
    expect(fn (): ActionResult => lynx()->run(SubmitForm::class, ['form' => 'survey', 'data' => ['comments' => 'Again']]))->toThrow(ValidationException::class);
});
