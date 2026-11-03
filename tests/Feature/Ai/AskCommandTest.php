<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use LynxCms\Ai\Agents\CmsAgent;

/*
 * lynx:ask shows the agent's plan and applies it only when confirmed, or at once with --apply;
 * a plan with nothing to do, or one that runs too long, changes nothing.
 */

beforeEach(function (): void {
    courseSite();
    config(['lynx.ai.plan_seconds' => 60]);
});

afterEach(function (): void {
    putenv('SHELL_INTERACTIVE');
});

/** The agent answers with one field to create, then its summary. */
function fakeClientFieldPlan(): void
{
    CmsAgent::fake([new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text', 'label' => 'Client']), 'Added a client field.']);
}

it('changes nothing when the agent plans nothing', function (): void {
    CmsAgent::fake(['There is nothing to change.']);

    $this->artisan('lynx:ask', ['prompt' => 'Is anything missing?'])->assertSuccessful();

    expect(lynx()->fields->find('client'))->toBeNull();
});

it('applies the plan once it is confirmed', function (): void {
    fakeClientFieldPlan();

    $this->artisan('lynx:ask', ['prompt' => 'Add a client field'])
        ->expectsConfirmation('Apply this plan?', 'yes')
        ->assertSuccessful();

    expect(lynx()->fields->find('client'))->not->toBeNull();
});

it('changes nothing when the plan is refused', function (): void {
    fakeClientFieldPlan();

    $this->artisan('lynx:ask', ['prompt' => 'Add a client field'])
        ->expectsConfirmation('Apply this plan?', 'no')
        ->assertSuccessful();

    expect(lynx()->fields->find('client'))->toBeNull();
});

it('applies without asking with --apply', function (): void {
    fakeClientFieldPlan();

    $this->artisan('lynx:ask', ['prompt' => 'Add a client field', '--apply' => true])->assertSuccessful();

    expect(lynx()->fields->find('client'))->not->toBeNull();
});

it('fails and writes nothing when the plan runs too long', function (): void {
    CmsAgent::fake(function (): ToolCall|string {
        static $step = 0;

        return match ($step++) {
            0 => new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text']),
            1 => (function (): ToolCall {
                Carbon::setTestNow(now()->addSeconds(61));

                return new ToolCall('2', 'fields_create', ['name' => 'budget', 'type' => 'integer']);
            })(),
            default => 'Done.',
        };
    });

    $this->artisan('lynx:ask', ['prompt' => 'Add client and budget fields', '--apply' => true])->assertFailed();

    expect(lynx()->fields->find('client'))->toBeNull()
        ->and(lynx()->fields->find('budget'))->toBeNull();
});

it('sends the files it is given to the agent', function (): void {
    $file = testingPath('brief').'.md';
    File::ensureDirectoryExists(dirname($file));
    File::put($file, "# Brief\n\nAdd a client field.");
    fakeClientFieldPlan();

    $this->artisan('lynx:ask', ['prompt' => 'Do what the brief says', '--file' => [$file], '--apply' => true])->assertSuccessful();

    CmsAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->count() === 1);
    File::delete($file);
});

it('asks what to do when no prompt is given', function (): void {
    fakeClientFieldPlan();
    putenv('SHELL_INTERACTIVE=1');

    $this->artisan('lynx:ask', ['--apply' => true])
        ->expectsQuestion('What should the agent do?', 'Add a client field')
        ->assertSuccessful();

    expect(lynx()->fields->find('client'))->not->toBeNull();
});
