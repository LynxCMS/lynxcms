<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Search\SearchInspectTool;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;
use Workbench\App\Models\User;

/*
 * search.manage (ADR-0120): rebuilding the search index and looking into it, for people and agents.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha');
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'indexer', 'grants' => [['permission' => 'search.manage']]]);
    lynx()->run(SaveRole::class, ['name' => 'schemer', 'grants' => [['permission' => 'schema.manage'], ['permission' => 'pages.view']]]);
    DB::table('users')->insert([
        ['id' => 3, 'name' => 'Indexer', 'email' => 'indexer@example.com', 'password' => 'x'],
        ['id' => 4, 'name' => 'Schemer', 'email' => 'schemer@example.com', 'password' => 'x'],
    ]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['indexer']]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['schemer']]);
});

it('lets search.manage rebuild the index, and no longer schema.manage', function (): void {
    expect(lynx()->actingAs(Actor::user(3), fn (): mixed => lynx()->run(RebuildSearchIndex::class, [])->value)['pages'])->toBeGreaterThan(0)
        ->and(fn (): mixed => lynx()->actingAs(Actor::user(4), fn (): ActionResult => lynx()->run(RebuildSearchIndex::class, [])))->toThrow(AuthorizationException::class);
});

it('offers search_inspect to MCP clients and the agent', function (): void {
    expect(app(ReadToolRegistry::class)->all())->toContain(SearchInspectTool::class);
});

it('reports the index and a page\'s rows to search_inspect', function (): void {
    LynxServer::actingAs(User::query()->find(3))->tool(new ModuleReadTool(SearchInspectTool::class), ['page' => (string) $this->alpha->id])
        ->assertOk()->assertSee('plurals')->assertSee('course')->assertSee('alpha');
});

it('refuses search_inspect without search.manage', function (): void {
    LynxServer::actingAs(User::query()->find(4))->tool(new ModuleReadTool(SearchInspectTool::class), [])
        ->assertHasErrors(['search.manage']);
});
