<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RebuildPageReferences;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Support\Tables;

/*
 * page_references.rebuild (ADR-0145): compares the index with the stored values and fixes the rows that differ.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    $this->alpha->related = [(int) $this->beta->id, (int) $this->gamma->id];
    $this->alpha->save();
    // Out of step: one row lost, one row that no stored value holds.
    DB::table(Tables::PAGE_REFERENCES)->where('linked_page_id', $this->gamma->id)->delete();
    DB::table(Tables::PAGE_REFERENCES)->insert(['page_id' => $this->beta->id, 'linked_page_id' => $this->alpha->id, 'field_id' => lynx()->fields->get('related')->id, 'kind' => 'field', 'sort' => 0]);
});

/** @return list<array{0: int, 1: int}> [page, linked page] for every row */
function indexPairs(): array
{
    return DB::table(Tables::PAGE_REFERENCES)->orderBy('page_id')->orderBy('sort')->get()
        ->map(fn (object $row): array => [(int) $row->page_id, (int) $row->linked_page_id])->all();
}

it('fixes the rows that differ from the stored values', function (): void {
    $totals = lynx()->run(RebuildPageReferences::class)->arrayValue();

    expect($totals)->toBe(['pages' => 2, 'added' => 1, 'removed' => 1, 'resorted' => 0])
        ->and(indexPairs())->toBe([[(int) $this->alpha->id, (int) $this->beta->id], [(int) $this->alpha->id, (int) $this->gamma->id]]);
});

it('lists the differences on a dry run and changes nothing', function (): void {
    $before = indexPairs();

    $result = lynx()->run(RebuildPageReferences::class, dryRun: true);

    expect($result->changes->all()[0]->data)->toBe(['pages' => 2, 'added' => 1, 'removed' => 1, 'resorted' => 0])
        ->and(indexPairs())->toBe($before);
});

it('rolls back when a hook halts it', function (): void {
    $before = indexPairs();
    lynx()->hook(RebuildPageReferences::class)->after(fn (): never => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(RebuildPageReferences::class))->toThrow(ActionHalted::class)
        ->and(indexPairs())->toBe($before);
});

it('needs page_references.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.edit'], ['permission' => 'pages.view']]]);
    DB::table('users')->insert(['id' => 3, 'name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'x']);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['editor']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(3), fn (): ActionResult => lynx()->run(RebuildPageReferences::class)))->toThrow(AuthorizationException::class);
});

it('runs from Artisan, with a dry run', function (): void {
    $before = indexPairs();

    expect(Artisan::call('lynx:page-references:rebuild', ['--dry-run' => true]))->toBe(0)
        ->and(indexPairs())->toBe($before)
        ->and(Artisan::call('lynx:page-references:rebuild'))->toBe(0)
        ->and(indexPairs())->toHaveCount(2);
});

it('fills the index when its migration runs on a site with content', function (): void {
    $migration = require dirname(__DIR__, 3).'/packages/core/database/migrations/2026_11_23_000000_create_lynx_page_references.php';
    Schema::drop(Tables::PAGE_REFERENCES);

    $migration->up();

    expect(indexPairs())->toBe([[(int) $this->alpha->id, (int) $this->beta->id], [(int) $this->alpha->id, (int) $this->gamma->id]]);
});
