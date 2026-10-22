<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\LockEdit;
use LynxCms\Core\Actions\Pages\UnlockEdit;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
});

function lockedCourse(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

function asUser(int $user, Closure $do): mixed
{
    return lynx()->actingAs(Actor::user($user), $do);
}

function lockFor(int $user): array
{
    return asUser($user, fn (): mixed => lynx()->run(LockEdit::class, ['page' => lockedCourse()->id])->value);
}

it('gives the edit lock to the first editor and tells the second who has it', function (): void {
    expect(lockFor(5)['holder'])->toBe('user:5')
        ->and(fn (): array => lockFor(6))->toThrow(ValidationException::class, 'being edited by user:5');
});

it('renews the lock for its holder', function (): void {
    $first = lockFor(5)['expires_at'];
    $this->travel(30)->seconds();

    expect(lockFor(5)['expires_at'])->not->toBe($first);
});

it('lets another editor take a lock that expired', function (): void {
    lockFor(5);
    $this->travel(config('lynx.edit_lock_seconds') + 1)->seconds();

    expect(lockFor(6)['holder'])->toBe('user:6');
});

it('refuses saves from anyone but the holder while the page is being edited', function (): void {
    lockFor(5);

    expect(fn (): mixed => asUser(6, function () {
        $page = lockedCourse();
        $page->title = 'Changed by 6';

        return $page->save();
    }))->toThrow(ValidationException::class, 'being edited by user:5')
        ->and(fn (): mixed => lynx()->actingAs(Actor::mcp(), fn () => lynx()->pages->setStatus(lockedCourse(), add: ['hidden'])))->toThrow(ValidationException::class, 'being edited');

    $saved = asUser(5, function () {
        $page = lockedCourse();
        $page->title = 'Changed by 5';

        return $page->save();
    });
    expect((string) $saved->get('title'))->toBe('Changed by 5');
});

it('releases the lock for its holder, and for superusers', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['editor']]);
    lynx()->run(AssignRoles::class, ['user' => '6', 'roles' => ['editor']]);
    lockFor(5);

    expect(fn (): mixed => asUser(6, fn (): ActionResult => lynx()->run(UnlockEdit::class, ['page' => lockedCourse()->id])))->toThrow(ValidationException::class, 'held by user:5');

    asUser(5, fn (): ActionResult => lynx()->run(UnlockEdit::class, ['page' => lockedCourse()->id]));
    expect(lynx()->pages->editLock(lockedCourse()))->toBeNull();

    lockFor(5);
    asUser(1, fn (): ActionResult => lynx()->run(UnlockEdit::class, ['page' => lockedCourse()->id]));
    expect(lynx()->pages->editLock(lockedCourse()))->toBeNull();
});

it('reports the current lock, and none once it expired', function (): void {
    lockFor(5);
    expect(lynx()->pages->editLock(lockedCourse())['holder'])->toBe('user:5');

    $this->travel(config('lynx.edit_lock_seconds') + 1)->seconds();
    expect(lynx()->pages->editLock(lockedCourse()))->toBeNull();
});

it('validates, previews and rolls back', function (): void {
    expect(fn (): ActionResult => lynx()->run(LockEdit::class, []))->toThrow(ValidationException::class);

    lynx()->run(LockEdit::class, ['page' => lockedCourse()->id], dryRun: true);
    expect(lynx()->pages->editLock(lockedCourse()))->toBeNull();

    lynx()->hook(LockEdit::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): array => lockFor(5))->toThrow(ActionHalted::class)
        ->and(lynx()->pages->editLock(lockedCourse()))->toBeNull();
});
