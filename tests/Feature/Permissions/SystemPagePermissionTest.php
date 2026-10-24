<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\PageStatus;

/*
 * System pages skip the site's family rules and hide from the page tree, so only people with
 * system.manage may create one or turn a page into one.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'course-manager', 'grants' => [['permission' => 'pages.create'], ['permission' => 'pages.edit'], ['permission' => 'pages.publish'], ['permission' => 'pages.status']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['course-manager']]);
});

function systemCourse(Actor $actor): mixed
{
    return lynx()->actingAs($actor, fn (): ActionResult => lynx()->run(SavePage::class, [
        'template' => 'course', 'parent' => '/courses/', 'names' => ['default' => 'hidden-course'], 'values' => ['title' => 'Hidden'], 'status' => ['system'],
    ]));
}

it('refuses a system page from someone without system.manage', function (): void {
    expect(fn (): mixed => systemCourse(Actor::user(5)))->toThrow(AuthorizationException::class, '[system.manage]');
});

it('never turns an existing page into a system page', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');

    lynx()->actingAs(Actor::user(5), fn (): ActionResult => lynx()->run(SavePage::class, ['id' => $alpha->id, 'status' => ['system']]));

    expect(lynx()->pages->get('/courses/alpha/')?->hasStatus(PageStatus::SYSTEM))->toBeFalse();
});

it('lets a superuser create a system page', function (): void {
    expect(systemCourse(Actor::user(1))->value->hasStatus(PageStatus::SYSTEM))->toBeTrue();
});
