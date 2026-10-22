<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RunSchedules;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->setLanguageName('pt', 'alfa', active: false);
    $alpha->save();
});

function alpha(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

it('publishes a language at the scheduled time, and not before', function (): void {
    lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDay());

    lynx()->run(RunSchedules::class);
    expect(alpha()->isActiveIn('pt'))->toBeFalse();
    $this->get('/pt/cursos/alfa/')->assertNotFound();

    $this->travel(25)->hours();
    lynx()->run(RunSchedules::class);

    expect(alpha()->isActiveIn('pt'))->toBeTrue()
        ->and(lynx()->pages->schedules(alpha()))->toBe([]);
    $this->get('/pt/cursos/alfa/')->assertOk();
});

it('unpublishes the page in the default language at the scheduled time', function (): void {
    lynx()->pages->schedule(alpha(), 'default', unpublishAt: now()->addHour());

    $this->travel(2)->hours();
    $this->artisan('lynx:schedule:run')->expectsOutputToContain('1 done')->assertSuccessful();

    expect(alpha()->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue();
});

it('lists, replaces and clears a page\'s schedule', function (): void {
    $at = now()->addDay()->startOfMinute();
    lynx()->pages->schedule(alpha(), 'pt', publishAt: $at, unpublishAt: $at->copy()->addWeek());
    lynx()->pages->schedule(alpha(), 'pt', publishAt: $at->copy()->addHour());

    expect(array_map(fn (array $entry): array => [$entry['language'], $entry['action'], $entry['at']->toIso8601String()], lynx()->pages->schedules(alpha())))
        ->toBe([['pt', 'publish', $at->copy()->addHour()->toIso8601String()], ['pt', 'unpublish', $at->copy()->addWeek()->toIso8601String()]]);

    lynx()->pages->schedule(alpha(), 'pt', clear: true);
    expect(lynx()->pages->schedules(alpha()))->toBe([]);
});

it('refuses times in the past, unpublishing before publishing, and unknown languages', function (): void {
    expect(fn () => lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->subMinute()))->toThrow(ValidationException::class, 'future')
        ->and(fn () => lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDays(2), unpublishAt: now()->addDay()))->toThrow(ValidationException::class, 'after')
        ->and(fn () => lynx()->pages->schedule(alpha(), 'xx', publishAt: now()->addDay()))->toThrow(ValidationException::class)
        ->and(fn (): ActionResult => lynx()->run(SchedulePage::class, []))->toThrow(ValidationException::class);
});

it('needs pages.publish on the page', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(5), fn () => lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDay())))
        ->toThrow(AuthorizationException::class, '[pages.publish]');
});

it('marks an entry that cannot run as failed, says why, and runs the others', function (): void {
    $beta = createCourses('beta')['beta'];
    lynx()->pages->schedule(alpha(), 'default', unpublishAt: now()->addMinute());
    lynx()->pages->schedule($beta, 'default', unpublishAt: now()->addMinute());
    lynx()->pages->setStatus(alpha(), add: ['locked']);

    $this->travel(5)->minutes();
    $result = lynx()->run(RunSchedules::class)->value;

    expect($result)->toBe(['done' => 1, 'failed' => 1])
        ->and(lynx()->pages->get($beta->id)?->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and(lynx()->pages->schedules(alpha())[0]['error'])->toContain('locked');

    expect(lynx()->run(RunSchedules::class)->value)->toBe(['done' => 0, 'failed' => 0]);
});

it('runs every minute from Laravel\'s scheduler', function (): void {
    $events = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

    expect($events->contains(fn (string $command): bool => str_contains($command, 'lynx:schedule:run')))->toBeTrue();
});

it('previews a schedule without saving it, and rolls back when halted', function (): void {
    expect(lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDay(), dryRun: true))->toBeInstanceOf(ActionResult::class)
        ->and(lynx()->pages->schedules(alpha()))->toBe([]);

    lynx()->hook(SchedulePage::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn () => lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDay()))->toThrow(ActionHalted::class)
        ->and(lynx()->pages->schedules(alpha()))->toBe([]);
});

it('forgets the schedule of a deleted page', function (): void {
    lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDay());
    $id = alpha()->id;
    lynx()->pages->delete($id);

    expect(DB::table('lynx_schedules')->where('page_id', $id)->exists())->toBeFalse();
});

it('schedules every language at once with language "*"', function (): void {
    $at = now()->addDay()->startOfMinute();

    $result = lynx()->run(SchedulePage::class, ['page' => alpha()->id, 'language' => SchedulePage::ALL_LANGUAGES, 'publish_at' => $at->toIso8601String()])->value;

    expect($result)->toBe(['default' => ['publish' => $at->toIso8601String()], 'pt' => ['publish' => $at->toIso8601String()]])
        ->and(array_map(fn (array $entry): array => [$entry['language'], $entry['action']], lynx()->pages->schedules(alpha())))
        ->toBe([['default', 'publish'], ['pt', 'publish']]);
});

it('stores nothing when one language refuses an all-languages schedule', function (): void {
    lynx()->pages->schedule(alpha(), 'pt', publishAt: now()->addDays(3));

    expect(fn (): ActionResult => lynx()->run(SchedulePage::class, ['page' => alpha()->id, 'language' => SchedulePage::ALL_LANGUAGES, 'unpublish_at' => now()->addDay()->toIso8601String()]))
        ->toThrow(ValidationException::class, 'after')
        ->and(array_map(fn (array $entry): string => $entry['language'].' '.$entry['action'], lynx()->pages->schedules(alpha())))->toBe(['pt publish']);
});
