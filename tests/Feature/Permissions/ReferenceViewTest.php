<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * A save may add a reference (a page field's id, or a rich text link's data-page) only to a page
 * the actor may view: the admin and the tools show the title and address of what a field holds.
 * Pages the field already holds are kept, as with its templates.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'News';
    $news->save();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $courses = (int) lynx()->pages->get('/courses/')->id;
    lynx()->run(SaveRole::class, ['name' => 'courses-editor', 'grants' => [
        ['permission' => 'pages.view', 'branches' => [$courses]],
        ['permission' => 'pages.edit', 'branches' => [$courses]],
    ]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['courses-editor']]);
});

/**
 * @param  array<string, mixed>  $values
 * @return array<string, mixed> the save's errors, empty when it succeeded
 */
function saveAlphaAs(array $values): array
{
    try {
        lynx()->actingAs(Actor::user(5), fn (): mixed => lynx()->run(SavePage::class, ['id' => lynx()->pages->get('/courses/alpha/')->id, 'values' => $values]));
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

function referencedId(string $path): int
{
    return (int) lynx()->pages->get($path)->id;
}

it('refuses a reference to a page the actor may not view', function (): void {
    expect(saveAlphaAs(['related' => [referencedId('/news/')]]))->toHaveKey('values.related');
});

it('refuses a reference to a page that does not exist', function (): void {
    expect(saveAlphaAs(['related' => [99999]]))->toHaveKey('values.related');
});

it('accepts a reference to a page the actor may view', function (): void {
    expect(saveAlphaAs(['related' => [referencedId('/courses/beta/')]]))->toBe([]);
});

it('keeps a page the field already holds, though the actor may not view it', function (): void {
    lynx()->run(SavePage::class, ['id' => referencedId('/courses/alpha/'), 'values' => ['related' => [referencedId('/news/')]]]);

    expect(saveAlphaAs(['related' => [referencedId('/news/'), referencedId('/courses/beta/')]]))->toBe([]);
});

it('refuses a rich text link to a page the actor may not view', function (): void {
    $body = '<p><a href="/news" data-page="'.referencedId('/news/').'">News</a></p>';

    expect(saveAlphaAs(['body' => ['default' => $body]]))->toHaveKey('values.body');
});

it('lets an actor who may view every page reference any page', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'courses-editor', 'grants' => [
        ['permission' => 'pages.view'],
        ['permission' => 'pages.edit', 'branches' => [referencedId('/courses/')]],
    ]]);

    expect(saveAlphaAs(['related' => [referencedId('/news/')]]))->toBe([]);
});
