<?php

declare(strict_types=1);

use App\Listeners\SendEnquiryToCrm;
use App\Listeners\SubscribeToNewsletter;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionReceived;

/*
 * Chapter 6 of the training site guide: the enquiry form on course pages, the newsletter form in
 * the footer, and the listeners that pass them on to the CRM and the mailing service.
 */

require_once __DIR__.'/training.php';

beforeEach(function (): void {
    trainingSite();
    RateLimiter::clear('lynx-forms');
    config(['atlas.crm' => ['url' => 'https://crm.test/api', 'token' => 'crm-secret'], 'atlas.newsletter' => ['url' => 'https://mail.test/v1', 'token' => 'mail-secret']]);
    $this->withoutVite();
});

function trainingEnquiry(string $path, string $language = 'en'): Testable
{
    return lynx()->language($language, fn () => Livewire::test(FormComponent::class, ['form' => 'enquiry', 'pageId' => lynx()->pages->get($path)?->id]));
}

it('shows the enquiry form on course pages and the newsletter form on every page', function (): void {
    $this->get('/courses/sports-nutrition')->assertOk()->assertSee('Questions about this course?')->assertSee('Your question');
    $this->get('/pt/cursos/nutricao-desportiva')->assertOk()->assertSee('A sua pergunta')->assertSee('Enviem-me novidades');
});

it('keeps an enquiry with the course it came from, and tells the front desk', function (): void {
    Notification::fake();

    $form = trainingEnquiry('/courses/sports-nutrition/')
        ->set('data.name', 'Rita Sousa')->set('data.email', 'rita@example.com')->set('data.goal', 'teach')
        ->set('data.message', 'Is it online too?')->set('data.consent', true);
    $this->travel(5)->seconds(); // the honeypot refuses forms sent faster than a person types
    $form->call('submit')->assertHasNoErrors()->assertSee('We reply within one working day');

    $submission = Submission::query()->sole();
    expect(lynx()->pages->get($submission->page_id)?->path())->toBe('courses/sports-nutrition');
    Notification::assertSentOnDemand(SubmissionReceived::class, fn (SubmissionReceived $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'hello@atlas-training.example');
});

it('refuses an enquiry without a question or consent', function (): void {
    $form = trainingEnquiry('/courses/sports-nutrition/')->set('data.name', 'Rita')->set('data.email', 'rita@example.com');
    $this->travel(5)->seconds();
    $form->call('submit')->assertHasErrors(['data.message', 'data.consent']);

    expect(Submission::query()->count())->toBe(0);
});

it('sends an enquiry to the CRM with its course', function (): void {
    Http::fake(['crm.test/*' => Http::response(['id' => 1], 201)]);
    $form = trainingEnquiry('/courses/sports-nutrition/', 'pt')
        ->set('data.name', 'Rita Sousa')->set('data.email', 'rita@example.com')->set('data.message', 'Online?')->set('data.consent', true);
    $this->travel(5)->seconds();
    $form->call('submit');

    new SendEnquiryToCrm()->handle(new FormSubmitted(Submission::query()->sole()->id));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://crm.test/api/leads'
        && $request['course'] === 'Sports nutrition' && $request['language'] === 'pt' && $request['name'] === 'Rita Sousa');
});

it('subscribes newsletter sign-ups in their language, and mails nobody', function (): void {
    Notification::fake();
    Http::fake(['mail.test/*' => Http::response([], 201)]);

    $form = lynx()->language('pt', fn () => Livewire::test(FormComponent::class, ['form' => 'newsletter', 'pageId' => lynx()->pages->get('/')?->id]))
        ->set('data.email', 'joao@example.com')->set('data.consent', true);
    $this->travel(5)->seconds();
    $form->call('submit')->assertHasNoErrors();

    new SubscribeToNewsletter()->handle(new FormSubmitted(Submission::query()->sole()->id));

    Http::assertSent(fn (ClientRequest $request): bool => $request['email'] === 'joao@example.com' && in_array('lang-pt', $request['tags'], true));
    Notification::assertNothingSent();
});

it('leaves other forms to other listeners', function (): void {
    Http::fake();
    $form = Livewire::test(FormComponent::class, ['form' => 'newsletter'])->set('data.email', 'a@example.com')->set('data.consent', true);
    $this->travel(5)->seconds();
    $form->call('submit');

    new SendEnquiryToCrm()->handle(new FormSubmitted(Submission::query()->sole()->id));

    Http::assertNothingSent();
});

it('sends nothing when the CRM is not configured, as on a developer\'s machine', function (): void {
    config(['atlas.crm.url' => null]);
    Http::fake();

    new SendEnquiryToCrm()->handle(new FormSubmitted(1));

    Http::assertNothingSent();
});
