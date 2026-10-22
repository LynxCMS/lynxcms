<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Support\Tables;

beforeEach(function (): void {
    courseSite();
    createCourses(...array_map(fn (int $n): string => "course-{$n}", range(1, 30)));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/** @param  array<string, mixed>  $grant */
function deleterWith(array $grant): Actor
{
    lynx()->run(SaveRole::class, ['name' => 'deleter', 'grants' => [['permission' => 'pages.delete'] + $grant]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['deleter']]);

    return Actor::user(5);
}

/** Queries reading one page's ancestors: the per page check a large branch must not need. */
function ancestryLookups(Closure $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $sql = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return count(array_filter($sql, fn (string $query): bool => str_contains($query, Tables::PAGE_CLOSURE) && str_contains($query, 'descendant_id" = ?')));
}

it('checks a whole branch against branch limited grants without reading each page\'s ancestors', function (): void {
    $deleter = deleterWith(['branches' => [lynx()->pages->get('/courses/')->id]]);

    expect(ancestryLookups(fn (): mixed => lynx()->actingAs($deleter, fn () => lynx()->pages->delete('/courses/'))))->toBeLessThan(3)
        ->and(lynx()->pages->get('/courses/'))->toBeNull();
});

it('names a page of the branch the grants do not cover, and deletes nothing', function (): void {
    $deleter = deleterWith(['templates' => ['course']]);

    expect(fn (): mixed => lynx()->actingAs($deleter, fn () => lynx()->pages->delete('/courses/')))
        ->toThrow(AuthorizationException::class, 'on page [/courses]')
        ->and(lynx()->pages->count('parent=/courses/, include=all'))->toBe(30);
});
