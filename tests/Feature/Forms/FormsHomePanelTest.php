<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * The forms module's panel on the admin home screen: the newest unread submissions, for those
 * who may see submissions.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function newQuoteSubmission(string $name): Submission
{
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => ['type' => 'film', 'name' => $name, 'email' => 'someone@example.com', 'consent' => true]]);

    return Submission::query()->where('data->name', $name)->sole();
}

it('lists the new submissions, each linking to it', function (): void {
    $ana = newQuoteSubmission('Ana Lima');
    $rui = newQuoteSubmission('Rui Costa');
    lynx()->run(UpdateSubmission::class, ['submission' => $rui->uuid, 'status' => 'read']);

    $this->get('/lynx')->assertOk()
        ->assertSee('data-home-panel="forms"', false)
        ->assertSee('href="'.route('lynx.admin.forms.show', $ana->uuid).'"', false)
        ->assertDontSee('href="'.route('lynx.admin.forms.show', $rui->uuid).'"', false);
});

it('shows an empty state when nothing is new', function (): void {
    $this->get('/lynx')->assertOk()
        ->assertSee('data-home-panel="forms"', false)
        ->assertDontSee('data-home-submission', false);
});

it('keeps the panel from users who may not see submissions', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['editor']]);
    $this->actingAs(new GenericUser(['id' => 3]));

    $this->get('/lynx')->assertOk()->assertDontSee('data-home-panel="forms"', false);
});
