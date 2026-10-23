<?php

declare(strict_types=1);

use App\Models\Booking;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Results\FormResults;
use Workbench\App\Models\User;

/*
 * Chapter 9 of the training site guide: the course survey staff build in the form builder, which
 * members who booked answer once each, and its results per course.
 */

require_once __DIR__.'/../Feature/Docs/Training/training.php';

beforeEach(function (): void {
    trainingSite();
    RateLimiter::clear('lynx-forms');
    $this->withoutVite();
    $this->materials = lynx()->pages->get('/courses/yoga-teacher-training/materials/');
});

function trainingSurveyMember(string $email): User
{
    $user = User::query()->forceCreate(['name' => 'Member', 'email' => $email, 'password' => Hash::make('long enough pass')]);
    $session = lynx()->pages->get('/courses/yoga-teacher-training/')->children('template=session')->first();
    Booking::query()->create(['uuid' => (string) Str::uuid(), 'session_id' => $session->id, 'user_id' => $user->id, 'name' => 'Member', 'email' => $email,
        'language' => 'en', 'amount' => 240000, 'currency' => 'eur', 'status' => 'paid', 'paid_at' => now()]);

    return $user;
}

function trainingAnswerSurvey(int $pageId, string $rating, string $pace): void
{
    $form = Livewire::test(FormComponent::class, ['form' => 'course-feedback', 'pageId' => $pageId])
        ->set('data.rating', $rating)->set('data.pace', $pace)->set('data.best', ['teacher']);
    test()->travel(5)->seconds();
    $form->call('submit')->assertHasNoErrors()->assertSee('Your answers help us plan the next course.');
}

it('shows the survey on the materials page to members who booked', function (): void {
    $this->actingAs(trainingSurveyMember('ana@example.com'));

    $this->get('/courses/yoga-teacher-training/materials')->assertOk()->assertSee('Tell us how it went')->assertSee('How would you rate the course?');
});

it('takes one answer per member, and says so the second time', function (): void {
    $this->actingAs(trainingSurveyMember('ana@example.com'));
    trainingAnswerSurvey($this->materials->id, '5', 'right');

    Livewire::test(FormComponent::class, ['form' => 'course-feedback', 'pageId' => $this->materials->id])
        ->assertSee('You have already answered this form.')->assertDontSee('How would you rate the course?');
});

it('sums the answers up for the course they came from', function (): void {
    foreach ([['ana@example.com', '5', 'right'], ['rui@example.com', '4', 'fast']] as [$email, $rating, $pace]) {
        $this->actingAs(trainingSurveyMember($email));
        trainingAnswerSurvey($this->materials->id, $rating, $pace);
    }

    $results = app(FormResults::class)->summarize(lynx()->forms()->get('course-feedback'), $this->materials->id);
    $byName = collect($results['questions'])->keyBy('name');

    expect($results['responses'])->toBe(2)
        ->and(array_column($byName['rating']['options'], 'count', 'value'))->toBe(['5' => 1, '4' => 1, '3' => 0, '2' => 0])
        ->and(array_column($byName['best']['options'], 'count', 'value'))->toBe(['teacher' => 2, 'materials' => 0, 'studio' => 0]);
});
