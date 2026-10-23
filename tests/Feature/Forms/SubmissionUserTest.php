<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Admin\Livewire\SubmissionView;
use LynxCms\Forms\Agents\GetSubmissionTool;
use LynxCms\Forms\Models\Submission;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;
use Workbench\App\Models\User;

/*
 * A submission sent by a signed-in visitor, such as a member, keeps who sent it: the inbox, the
 * CSV export and the agents' read tools show the account, and code can find a member's answers.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    $this->member = User::query()->forceCreate(['name' => 'Mia Member', 'email' => 'mia@example.com', 'password' => Hash::make('long enough')]);
});

function sendQuoteAs(?User $user): Submission
{
    $send = fn (): Submission => lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => ['type' => 'film', 'name' => 'Mia', 'email' => 'answers@example.com', 'consent' => true]])->valueAs(Submission::class);

    $user === null ? auth()->logout() : test()->actingAs($user);

    return $send();
}

it('records the signed-in visitor who sent a submission', function (): void {
    $submission = sendQuoteAs($this->member);

    expect($submission->user_id)->toBe((string) $this->member->id)
        ->and($submission->user()?->getAuthIdentifier())->toBe($this->member->id);
});

it('records no one for a guest', function (): void {
    expect(sendQuoteAs(null)->user_id)->toBeNull();
});

it('finds a member\'s submissions', function (): void {
    sendQuoteAs($this->member);
    sendQuoteAs(null);

    expect(Submission::query()->fromUser($this->member->id)->count())->toBe(1);
});

it('shows the account in the inbox', function (): void {
    $submission = sendQuoteAs($this->member);
    $this->actingAs(new GenericUser(['id' => 99]));

    Livewire::test(SubmissionView::class, ['uuid' => $submission->uuid])->assertSee('Mia Member')->assertSee('mia@example.com');
});

it('adds the account to the CSV export', function (): void {
    sendQuoteAs($this->member);
    sendQuoteAs(null);
    $this->actingAs(new GenericUser(['id' => 99]));

    $csv = $this->get(route('lynx.admin.forms.export', 'quote'))->assertOk()->streamedContent();

    expect(explode("\n", $csv)[0])->toContain('Account')
        ->and($csv)->toContain('mia@example.com');
});

it('gives agents the account with the submission', function (): void {
    $submission = sendQuoteAs($this->member);
    $this->actingAs(new GenericUser(['id' => 99]));

    LynxServer::tool(new ModuleReadTool(GetSubmissionTool::class), ['submission' => $submission->uuid])
        ->assertOk()->assertSee('mia@example.com');
});
