<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\EmptyTrash;
use LynxCms\Core\Actions\Pages\RestorePage;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $lesson = lynx()->pages->new('course', '/courses/alpha/');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
});

function at(string $path): ?Page
{
    return lynx()->pages->get($path);
}

function trashRoot(): int
{
    return (int) lynx()->pages->findOne('template=trash, include=all')?->id;
}

function trashedIds(): array
{
    return trashRoot() === 0 ? [] : lynx()->pages->find('has_parent='.trashRoot().', include=all')->ids();
}

it('moves a branch to the trash, where visitors cannot reach it', function (): void {
    $alpha = at('/courses/alpha/');
    lynx()->pages->trash($alpha);

    expect(lynx()->pages->get($alpha->id)?->path())->toBeNull()
        ->and(count(trashedIds()))->toBe(2)
        ->and(lynx()->pages->get($alpha->id)?->name())->toBe($alpha->id.'-alpha');

    $this->get('/courses/alpha/')->assertNotFound();
    $this->get('/courses/alpha/lesson/')->assertNotFound();
    $this->get('/_trash/')->assertNotFound();
});

it('restores a branch where it was, with its name and URLs', function (): void {
    $alpha = at('/courses/alpha/');
    lynx()->pages->trash($alpha);
    lynx()->pages->restore($alpha->id);

    expect(at('/courses/alpha/')?->id)->toBe($alpha->id)
        ->and(at('/courses/alpha/lesson/'))->not->toBeNull()
        ->and(trashedIds())->toBe([]);

    $this->get('/courses/alpha/')->assertOk();
});

it('refuses to trash a branch with a locked page deep inside, naming that page, and moves nothing', function (): void {
    lynx()->pages->setStatus('/courses/alpha/lesson/', add: ['locked']);

    expect(fn () => lynx()->pages->trash('/courses/alpha/'))
        ->toThrow(ValidationException::class, 'Page [lesson] below [alpha] is locked. Unlock it first.')
        ->and(at('/courses/alpha/lesson/'))->not->toBeNull();
});

it('refuses to trash system pages and the home page', function (): void {
    lynx()->menus()->create('top', 'Top');

    expect(fn () => lynx()->pages->trash('/'))->toThrow(ValidationException::class, 'home page')
        ->and(fn () => lynx()->pages->trash((int) lynx()->menus()->root()?->id))->toThrow(ValidationException::class, 'system page')
        ->and(fn () => lynx()->pages->trash(trashRoot()))->toThrow(ValidationException::class);
});

it('refuses to trash the trash, or a page already in it', function (): void {
    $beta = at('/courses/beta/');
    lynx()->pages->trash($beta);

    expect(fn () => lynx()->pages->trash(trashRoot()))->toThrow(ValidationException::class, 'The trash cannot be trashed.')
        ->and(fn () => lynx()->pages->trash($beta->id))->toThrow(ValidationException::class, 'already in the trash');
});

it('leaves trashed pages out of page lists', function (): void {
    lynx()->pages->trash('/courses/alpha/');

    expect(lynx()->pages->find('template=course')->ids())->toBe([(int) at('/courses/beta/')?->id]);
});

it('restores a branch with locked pages', function (): void {
    $alpha = at('/courses/alpha/');
    lynx()->pages->trash($alpha);
    lynx()->pages->setStatus((int) lynx()->pages->find("has_parent={$alpha->id}, include=all")->first()?->id, add: ['locked']);

    lynx()->pages->restore($alpha->id);

    expect(at('/courses/alpha/lesson/')?->hasStatus(PageStatus::LOCKED))->toBeTrue();
});

it('asks for a new parent when the original one is gone', function (): void {
    $lesson = at('/courses/alpha/lesson/');
    lynx()->pages->trash($lesson);
    lynx()->pages->delete('/courses/alpha/');

    expect(fn () => lynx()->pages->restore($lesson->id))->toThrow(ValidationException::class, 'Choose a new parent');

    lynx()->pages->restore($lesson->id, parent: '/courses/beta/');
    expect(at('/courses/beta/lesson/')?->id)->toBe($lesson->id);
});

it('asks for a new parent when the original one is in the trash too', function (): void {
    $lesson = at('/courses/alpha/lesson/');
    lynx()->pages->trash($lesson);
    lynx()->pages->trash('/courses/alpha/');

    expect(fn () => lynx()->pages->restore($lesson->id))->toThrow(ValidationException::class, 'is in the trash');
});

it('empties the trash, keeping branches with locked pages and saying which pages block them', function (): void {
    $alpha = at('/courses/alpha/');
    $beta = at('/courses/beta/');
    lynx()->pages->trash($alpha);
    lynx()->pages->trash($beta);
    $lesson = (int) lynx()->pages->find("has_parent={$alpha->id}, include=all")->first()?->id;
    lynx()->pages->setStatus($lesson, add: ['locked']);

    $result = lynx()->run(EmptyTrash::class)->value;

    expect($result['deleted'])->toBe([$beta->id])
        ->and($result['blocked'])->toBe([['page' => $alpha->id, 'locked' => [$lesson]]])
        ->and(lynx()->pages->get($beta->id))->toBeNull()
        ->and(trashedIds())->toContain($alpha->id);
});

it('empties one trashed branch', function (): void {
    $alpha = at('/courses/alpha/');
    $beta = at('/courses/beta/');
    lynx()->pages->trash($alpha);
    lynx()->pages->trash($beta);

    lynx()->pages->emptyTrash($alpha->id);

    expect(lynx()->pages->get($alpha->id))->toBeNull()->and(trashedIds())->toBe([$beta->id]);
});

it('lets branch limited editors trash, unlock and empty only their own pages', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $courses = (int) at('/courses/')?->id;
    lynx()->run(SaveRole::class, ['name' => 'course-cleaner', 'grants' => [
        ['permission' => 'pages.delete', 'branches' => [$courses]],
        ['permission' => 'pages.status', 'branches' => [$courses]],
    ]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['course-cleaner']]);
    $cleaner = Actor::user(5);
    $alpha = at('/courses/alpha/');
    $topic = lynx()->pages->new('courses', '/');
    $topic->name = 'news';
    $topic->title = 'News';
    $topic->save();

    lynx()->actingAs($cleaner, fn () => lynx()->pages->trash($alpha));
    lynx()->pages->setStatus($alpha->id, add: ['locked']);
    lynx()->actingAs($cleaner, fn () => lynx()->pages->setStatus($alpha->id, remove: ['locked']));
    lynx()->pages->trash('/news/');

    expect(fn (): mixed => lynx()->actingAs($cleaner, fn () => lynx()->pages->trash('/courses/')))->not->toThrow(AuthorizationException::class);
    $result = lynx()->actingAs($cleaner, fn (): mixed => lynx()->run(EmptyTrash::class)->value);

    expect($result['deleted'])->toContain($alpha->id)
        ->and($result['denied'])->toBe([(int) lynx()->pages->find('has_parent='.trashRoot().', include=all, name%=news')->first()?->id]);
});

it('previews trashing without moving anything', function (): void {
    $result = lynx()->pages->trash('/courses/alpha/', dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->count())->toBeGreaterThan(0)
        ->and(at('/courses/alpha/'))->not->toBeNull();
});

it('rolls back a restore that fails half way', function (): void {
    $alpha = at('/courses/alpha/');
    lynx()->pages->trash($alpha);
    lynx()->hook(RestorePage::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn () => lynx()->pages->restore($alpha->id))->toThrow(ActionHalted::class)
        ->and(trashedIds())->toContain($alpha->id);
});

it('validates its input', function (): void {
    expect(fn (): ActionResult => lynx()->run(TrashPage::class, []))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->pages->restore((int) at('/courses/beta/')?->id))->toThrow(ValidationException::class, 'not in the trash');
});

it('keeps pages.move and new pages out of the trash, so trash records stay true', function (): void {
    $beta = at('/courses/beta/');
    lynx()->pages->trash($beta);

    expect(fn () => lynx()->pages->move($beta->id, '/courses/'))->toThrow(ValidationException::class, 'pages.restore')
        ->and(fn () => lynx()->pages->move('/courses/alpha/', trashRoot()))->toThrow(ValidationException::class, 'pages.trash')
        ->and(fn () => lynx()->pages->move('/courses/alpha/', $beta->id))->toThrow(ValidationException::class, 'pages.trash');

    $page = lynx()->pages->new('course', $beta->id);
    $page->name = 'gamma';
    $page->title = 'Gamma';

    expect(fn () => $page->save())->toThrow(ValidationException::class, 'trash');
});

it('keeps the trash last among its siblings, like ProcessWire', function (): void {
    lynx()->pages->trash('/courses/beta/');
    $news = lynx()->pages->new('courses', '/');
    $news->name = 'news';
    $news->title = 'News';
    $news->save();
    $last = fn (): ?string => lynx()->pages->find('parent=/, include=all, sort=sort')->last()?->template()->name;

    expect($last())->toBe('trash');

    lynx()->pages->sort('/courses/', 99);
    expect($last())->toBe('trash');
});
