<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RunSchedules;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\Page;

/*
 * A draft can go live by itself at a set time (ADR-0092): the whole page's draft, whatever the
 * language, published by the scheduler as an editor would publish it.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->pages->saveDraft('/courses/alpha/', ['summary' => ['default' => 'The new summary']]);
});

function draftedAlpha(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

it('publishes the draft at the scheduled time, and not before', function (): void {
    lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addDay());

    lynx()->run(RunSchedules::class);
    expect((string) draftedAlpha()->summary)->not->toBe('The new summary')
        ->and((lynx()->pages->draft(draftedAlpha()) !== null))->toBeTrue();

    $this->travel(25)->hours();
    lynx()->run(RunSchedules::class);

    expect((string) draftedAlpha()->summary)->toBe('The new summary')
        ->and((lynx()->pages->draft(draftedAlpha()) !== null))->toBeFalse()
        ->and(lynx()->pages->schedules(draftedAlpha()))->toBe([]);
});

it('publishes the draft as it is when the time comes', function (): void {
    lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addHour());
    lynx()->pages->saveDraft('/courses/alpha/', ['summary' => ['default' => 'Edited after scheduling']]);

    $this->travel(2)->hours();
    lynx()->run(RunSchedules::class);

    expect((string) draftedAlpha()->summary)->toBe('Edited after scheduling');
});

it('stores one entry for the whole page, whatever the language asked for', function (): void {
    lynx()->pages->schedule(draftedAlpha(), 'pt', publishDraftAt: now()->addDay());

    expect(array_map(fn (array $entry): array => [$entry['language'], $entry['action']], lynx()->pages->schedules(draftedAlpha())))
        ->toBe([['*', 'publish_draft']]);
});

it('refuses a page without a draft, and a time in the past', function (): void {
    lynx()->pages->discardDraft('/courses/alpha/');
    expect(fn () => lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addDay()))->toThrow(ValidationException::class, 'no draft');

    lynx()->pages->saveDraft('/courses/alpha/', ['summary' => ['default' => 'Again']]);
    expect(fn () => lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->subMinute()))->toThrow(ValidationException::class, 'future');
});

it('clears the entry when the draft is published or discarded by hand', function (string $how): void {
    lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addDay());

    $how === 'publish' ? lynx()->pages->publishDraft('/courses/alpha/') : lynx()->pages->discardDraft('/courses/alpha/');

    expect(lynx()->pages->schedules(draftedAlpha()))->toBe([]);
})->with(['publish', 'discard']);

it('fails, saying why, when the page is locked at the time', function (): void {
    lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addHour());
    lynx()->pages->setStatus('/courses/alpha/', add: ['locked']);

    $this->travel(2)->hours();
    $result = lynx()->run(RunSchedules::class)->arrayValue();

    expect($result)->toBe(['done' => 0, 'failed' => 1])
        ->and(lynx()->pages->schedules(draftedAlpha())[0]['error'])->not->toBeNull()
        ->and((lynx()->pages->draft(draftedAlpha()) !== null))->toBeTrue();
});

it('needs pages.edit as well as pages.publish', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'publisher', 'grants' => [['permission' => 'pages.publish']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['publisher']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(5), fn () => lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addDay())))
        ->toThrow(AuthorizationException::class, '[pages.edit]');
});

it('previews scheduling a draft without storing it', function (): void {
    $result = lynx()->run(SchedulePage::class, ['page' => '/courses/alpha/', 'publish_draft_at' => now()->addDay()->toIso8601String()], dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->all())->not->toBeEmpty()
        ->and(lynx()->pages->schedules(draftedAlpha()))->toBe([]);
});

it('says the draft went live on schedule in the page\'s activity', function (): void {
    lynx()->pages->schedule(draftedAlpha(), publishDraftAt: now()->addHour());
    $this->travel(2)->hours();
    lynx()->run(RunSchedules::class);

    $event = ActivityEvent::query()->where('action', RunSchedules::name())->latest('id')->firstOrFail();

    expect(collect($event->changes)->pluck('data.scheduled')->filter()->values()->all())->toBe(['publish_draft']);
});
