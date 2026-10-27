<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
});

function live(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

function draftTitle(string $title, int $price = 10): void
{
    lynx()->pages->saveDraft(live(), ['title' => ['default' => $title], 'price' => $price]);
}

it('keeps changes in a draft while the live page stays as it was', function (): void {
    draftTitle('Alpha draft');

    expect((string) live()->get('title'))->toBe('Alpha')
        ->and((string) lynx()->pages->draft(live())?->get('title'))->toBe('Alpha draft')
        ->and(lynx()->pages->draft(live())?->get('price'))->toBe(10);
});

it('starts a draft from the live values, in every language', function (): void {
    $page = live();
    $page->setLanguageValue('pt', 'title', 'Alfa');
    $page->save();

    lynx()->pages->saveDraft(live(), ['price' => 20]);

    expect((string) lynx()->pages->draft(live())?->getLanguageValue('pt', 'title'))->toBe('Alfa');
});

it('keeps one draft per page', function (): void {
    draftTitle('First');
    draftTitle('Second', 30);

    expect(DB::table('lynx_pages')->where('draft_of', live()->id)->count())->toBe(1)
        ->and((string) lynx()->pages->draft(live())?->get('title'))->toBe('Second');
});

it('keeps drafts out of selectors, counts and the tree', function (): void {
    draftTitle('Alpha draft');

    expect(lynx()->pages->find('template=course, include=all')->ids())->toBe([live()->id, (int) lynx()->pages->get('/courses/beta/')?->id])
        ->and(lynx()->pages->count('title%=draft, include=all'))->toBe(0)
        ->and(lynx()->pages->childCounts([(int) lynx()->pages->get('/courses/')?->id], 'include=all'))->toBe([(int) lynx()->pages->get('/courses/')?->id => 2])
        ->and(lynx()->pages->get('/')?->parentId)->toBeNull();
});

it('publishes a draft onto the live page, as a save with a revision', function (): void {
    draftTitle('Alpha published', 99);
    $revisions = count(lynx()->pages->revisions(live()));

    lynx()->pages->publishDraft(live());

    expect((string) live()->get('title'))->toBe('Alpha published')
        ->and(live()->get('price'))->toBe(99)
        ->and(lynx()->pages->draft(live()))->toBeNull()
        ->and(lynx()->pages->revisions(live()))->toHaveCount($revisions + 1);
});

it('discards a draft', function (): void {
    draftTitle('Never mind');
    lynx()->pages->discardDraft(live());

    expect(lynx()->pages->draft(live()))->toBeNull()
        ->and((string) live()->get('title'))->toBe('Alpha')
        ->and(DB::table('lynx_f_title')->whereNotIn('page_id', DB::table('lynx_pages')->pluck('id'))->exists())->toBeFalse();
});

it('validates draft values like a save', function (): void {
    expect(fn () => lynx()->pages->saveDraft(live(), ['title' => ['default' => '']]))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->pages->saveDraft(live(), ['nope' => 1]))->toThrow(ValidationException::class, 'nope')
        ->and(fn () => lynx()->pages->publishDraft(live()))->toThrow(ValidationException::class, 'no draft');
});

it('respects the live page\'s locks', function (): void {
    lynx()->pages->setStatus(live(), add: ['locked']);

    expect(fn () => draftTitle('Locked'))->toThrow(ValidationException::class, 'locked');
});

it('needs pages.edit on the live page', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['viewer']]);

    expect(fn () => lynx()->actingAs(Actor::user(5), fn () => draftTitle('Nope')))->toThrow(AuthorizationException::class);
});

it('deletes the draft with its page, and refuses a template switch while a draft exists', function (): void {
    lynx()->templates->add('lesson', fields: ['title']);
    draftTitle('Draft');

    expect(fn () => lynx()->pages->switchTemplate(live(), 'lesson'))->toThrow(ValidationException::class, 'draft');

    $id = live()->id;
    lynx()->pages->delete($id);
    expect(DB::table('lynx_pages')->where('draft_of', $id)->exists())->toBeFalse();
});

it('previews and rolls back', function (): void {
    lynx()->run(SaveDraft::class, ['page' => live()->id, 'values' => ['price' => 5]], dryRun: true);
    expect(lynx()->pages->draft(live()))->toBeNull();

    draftTitle('Draft');
    lynx()->hook(PublishDraft::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn () => lynx()->pages->publishDraft(live()))->toThrow(ActionHalted::class)
        ->and((string) live()->get('title'))->toBe('Alpha')
        ->and(lynx()->pages->draft(live()))->not->toBeNull();
});

it('files saving and discarding a draft under the live page in the activity log', function (): void {
    draftTitle('Alpha in draft');
    lynx()->pages->discardDraft(live());

    $actions = array_map(fn ($event): string => $event->action, lynx()->pages->activity(live()));

    expect($actions)->toContain('pages.save_draft')->toContain('pages.discard_draft');
});
