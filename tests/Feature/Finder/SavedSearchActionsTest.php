<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Finder\Actions\DeleteSearch;
use LynxCms\Finder\Actions\SaveSearch;
use LynxCms\Finder\Models\SavedSearch;

/*
 * Saved searches (ADR-0111): finder.save_search keeps a search under a name for the user who saved
 * it, shared with everyone who may use the finder when they hold finder.share; only its owner or a
 * superuser changes or deletes it.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(SaveRole::class, ['name' => 'sharer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'finder.share']]]);
    lynx()->run(SaveRole::class, ['name' => 'boss', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['viewer']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['sharer']]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['boss']]);
    $this->query = ['match' => 'all', 'groups' => [['match' => 'all', 'conditions' => [['subject' => 'price', 'operator' => 'gte', 'value' => '50']]]], 'sort' => 'title'];
});

function saveSearchAs(int $user, array $input): SavedSearch
{
    test()->actingAs(new GenericUser(['id' => $user]));

    return lynx()->run(SaveSearch::class, $input)->valueAs(SavedSearch::class);
}

it('saves a search for the user who saved it', function (): void {
    $search = saveSearchAs(2, ['name' => 'Dear courses', 'query' => $this->query]);

    expect($search->owner)->toBe('user:2')
        ->and($search->is_shared)->toBeFalse()
        ->and($search->query['groups'][0]['conditions'][0])->toMatchArray(['subject' => 'price', 'operator' => 'gte', 'value' => '50']);
});

it('refuses a search without a name', function (): void {
    saveSearchAs(2, ['name' => '', 'query' => $this->query]);
})->throws(ValidationException::class);

it('saves nothing on a dry run', function (): void {
    $this->actingAs(new GenericUser(['id' => 2]));
    lynx()->run(SaveSearch::class, ['name' => 'Dear courses', 'query' => $this->query], dryRun: true);

    expect(SavedSearch::query()->count())->toBe(0);
});

it('rolls back a search when a hook halts', function (): void {
    lynx()->hook(SaveSearch::class)->after(fn (): never => throw new ActionHalted('No.'));

    expect(fn (): SavedSearch => saveSearchAs(2, ['name' => 'Dear courses', 'query' => $this->query]))->toThrow(ActionHalted::class)
        ->and(SavedSearch::query()->count())->toBe(0);
});

it('shares a search only for users who may share', function (): void {
    expect(saveSearchAs(3, ['name' => 'Team', 'query' => $this->query, 'shared' => true])->is_shared)->toBeTrue()
        ->and(fn (): SavedSearch => saveSearchAs(2, ['name' => 'Mine', 'query' => $this->query, 'shared' => true]))->toThrow(AuthorizationException::class);
});

it('lets the owner rename a search, and refuses everyone else but a superuser', function (): void {
    $search = saveSearchAs(3, ['name' => 'Team', 'query' => $this->query, 'shared' => true]);

    expect(saveSearchAs(3, ['search' => $search->id, 'name' => 'Team courses', 'query' => $this->query, 'shared' => true])->name)->toBe('Team courses')
        ->and(fn (): SavedSearch => saveSearchAs(2, ['search' => $search->id, 'name' => 'Taken', 'query' => $this->query]))->toThrow(AuthorizationException::class)
        ->and(saveSearchAs(4, ['search' => $search->id, 'name' => 'Fixed', 'query' => $this->query, 'shared' => true])->owner)->toBe('user:3');
});

it('deletes a search for its owner only', function (): void {
    $search = saveSearchAs(3, ['name' => 'Team', 'query' => $this->query, 'shared' => true]);
    $this->actingAs(new GenericUser(['id' => 2]));

    expect(fn (): ActionResult => lynx()->run(DeleteSearch::class, ['search' => $search->id]))->toThrow(AuthorizationException::class);

    $this->actingAs(new GenericUser(['id' => 3]));
    lynx()->run(DeleteSearch::class, ['search' => $search->id], dryRun: true);
    expect(SavedSearch::query()->count())->toBe(1);

    lynx()->run(DeleteSearch::class, ['search' => $search->id]);
    expect(SavedSearch::query()->count())->toBe(0);
});

it('lists the user\'s own searches and the shared ones', function (): void {
    saveSearchAs(2, ['name' => 'Private', 'query' => $this->query]);
    saveSearchAs(3, ['name' => 'Team', 'query' => $this->query, 'shared' => true]);
    saveSearchAs(3, ['name' => 'Hidden', 'query' => $this->query]);

    expect(SavedSearch::visibleTo('user:2')->pluck('name')->all())->toBe(['Private', 'Team']);
});
