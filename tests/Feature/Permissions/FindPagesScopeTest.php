<?php

declare(strict_types=1);

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Agents\AgentToolkit;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'News';
    $news->save();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/**
 * @param  list<array<string, mixed>>  $grants
 * @return array<string, mixed>
 */
function findAs(array $grants, string $selector, ?int $limit = null): array
{
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => $grants]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['viewer']]);

    return lynx()->actingAs(Actor::mcp(5), fn (): array => app(AgentToolkit::class)->findPages($selector, limit: $limit));
}

/** @param  array<string, mixed>  $result */
function urls(array $result): array
{
    return array_column($result['pages'], 'url');
}

it('lists only pages inside the branches a grant allows, the branch page included', function (): void {
    $result = findAs([['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses')->id]]], 'include=all, sort=id');

    expect(urls($result))->toBe(['/courses', '/courses/alpha', '/courses/beta'])
        ->and($result['total'])->toBe(3);
});

it('lists only pages of the templates a grant allows', function (): void {
    expect(urls(findAs([['permission' => 'pages.view', 'templates' => ['topic']]], 'include=all')))->toBe(['/news']);
});

it('adds up several grants', function (): void {
    $result = findAs([
        ['permission' => 'pages.view', 'templates' => ['topic']],
        ['permission' => 'pages.view', 'templates' => ['course'], 'branches' => [lynx()->pages->get('/courses')->id]],
    ], 'include=all, sort=id');

    expect(urls($result))->toBe(['/courses/alpha', '/courses/beta', '/news']);
});

it('counts and pages within the scope', function (): void {
    $result = findAs([['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses')->id]]], 'include=all, sort=id', limit: 1);

    expect($result['total'])->toBe(3)->and(urls($result))->toBe(['/courses']);
});

it('does not limit superusers or unlimited grants', function (): void {
    expect(findAs([['permission' => 'pages.view']], 'include=all')['total'])->toBe(lynx()->pages->count('include=all'))
        ->and(lynx()->actingAs(Actor::mcp(1), fn (): array => app(AgentToolkit::class)->findPages('include=all'))['total'])->toBe(lynx()->pages->count('include=all'));
});
