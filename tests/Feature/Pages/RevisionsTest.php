<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
});

function revised(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

function retitle(string $title, ?int $price = null): void
{
    $page = revised();
    $page->title = $title;

    if ($price !== null) {
        $page->price = $price;
    }

    $page->save();
}

it('records the page\'s values after every save that changes them', function (): void {
    retitle('Alpha two', 100);
    retitle('Alpha three');

    $revisions = lynx()->pages->revisions(revised());

    expect($revisions)->toHaveCount(3)
        ->and(lynx()->pages->revisionValues($revisions[0]['id'])['default']['title'])->toBe('Alpha three')
        ->and(lynx()->pages->revisionValues($revisions[1]['id'])['*']['price'])->toBe(100)
        ->and($revisions[0]['created_by'])->toBe('cli');
});

it('records no revision when no value changes', function (): void {
    lynx()->pages->setStatus(revised(), add: ['hidden']);
    revised()->save();

    expect(lynx()->pages->revisions(revised()))->toHaveCount(1);
});

it('keeps the newest revisions only', function (): void {
    config(['lynx.revisions.keep' => 3]);

    foreach (range(1, 5) as $n) {
        retitle("Alpha {$n}");
    }

    $kept = lynx()->pages->revisions(revised());
    expect($kept)->toHaveCount(3)
        ->and(lynx()->pages->revisionValues($kept[2]['id'])['default']['title'])->toBe('Alpha 3');
});

it('restores a revision as a new save', function (): void {
    retitle('Alpha two', 100);
    $old = lynx()->pages->revisions(revised())[0]['id'];
    retitle('Alpha three', 200);

    lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $old]);

    expect((string) revised()->get('title'))->toBe('Alpha two')
        ->and(revised()->get('price'))->toBe(100)
        ->and(lynx()->pages->revisions(revised()))->toHaveCount(4);
});

it('restores only fields the template still has', function (): void {
    $old = lynx()->pages->revisions(revised())[0]['id'];
    DB::table('lynx_revisions')->where('id', $old)->update(['values' => json_encode(['default' => ['title' => 'From the past', 'gone' => 'x']])]);

    lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $old]);

    expect((string) revised()->get('title'))->toBe('From the past');
});

it('refuses revisions of another page, and needs pages.edit', function (): void {
    $beta = createCourses('beta')['beta'];
    $betaRevision = lynx()->pages->revisions($beta)[0]['id'];

    expect(fn (): ActionResult => lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $betaRevision]))->toThrow(ValidationException::class, 'not a revision of')
        ->and(fn (): ActionResult => lynx()->run(RestoreRevision::class, []))->toThrow(ValidationException::class);

    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['viewer']]);
    $own = lynx()->pages->revisions(revised())[0]['id'];

    expect(fn (): mixed => lynx()->actingAs(Actor::user(5), fn (): ActionResult => lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $own])))
        ->toThrow(AuthorizationException::class);
});

it('previews and rolls back a restore', function (): void {
    retitle('Alpha two');
    $first = lynx()->pages->revisions(revised())[1]['id'];

    lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $first], dryRun: true);
    expect((string) revised()->get('title'))->toBe('Alpha two');

    lynx()->hook(RestoreRevision::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(RestoreRevision::class, ['page' => revised()->id, 'revision' => $first]))->toThrow(ActionHalted::class)
        ->and((string) revised()->get('title'))->toBe('Alpha two');
});

it('forgets the revisions of a deleted page', function (): void {
    $id = revised()->id;
    lynx()->pages->delete($id);

    expect(DB::table('lynx_revisions')->where('page_id', $id)->exists())->toBeFalse();
});
