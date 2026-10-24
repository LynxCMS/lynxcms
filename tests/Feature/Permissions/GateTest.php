<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use LynxCms\Ai\Tools\ReadTool;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Permissions\Role;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\DescribeSchemaTool;
use LynxCms\Mcp\Tools\FindPagesTool;
use LynxCms\Mcp\Tools\GetPageTool;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
});

/**
 * @param  array<string, array{templates?: list<string>, branches?: list<int>}>  $grants
 */
function member(int $id, array $grants): GenericUser
{
    $role = Role::query()->create(['name' => "member-{$id}", 'label' => "Member {$id}"]);

    foreach ($grants as $permission => $scope) {
        $role->grants()->create(['permission' => $permission, 'templates' => $scope['templates'] ?? null, 'branches' => $scope['branches'] ?? null]);
    }

    $role->assign($id);

    return new GenericUser(['id' => $id]);
}

it('answers Laravel gate checks for Lynx permissions', function (): void {
    $editor = member(5, ['pages.edit' => ['templates' => ['course']]]);

    expect(Gate::forUser($editor)->allows('lynx.pages.edit', lynx()->pages->get('/courses/alpha/')))->toBeTrue()
        ->and(Gate::forUser($editor)->allows('lynx.pages.edit', lynx()->pages->get('/courses/')))->toBeFalse()
        ->and(Gate::forUser($editor)->allows('lynx.pages.delete'))->toBeFalse()
        ->and(Gate::forUser($editor)->allows('someOtherAbility'))->toBeFalse();
});

it('works with @can in Blade', function (): void {
    $this->actingAs(member(5, ['pages.edit' => []]));

    $html = Blade::render('@can("lynx.pages.edit") edit @endcan @cannot("lynx.pages.delete") no delete @endcannot');

    expect(preg_replace('/\s+/', ' ', trim($html)))->toBe('edit no delete');
});

it('opens the admin to users holding admin.access', function (): void {
    member(5, ['pages.view' => []]);
    $admin = member(6, ['admin.access' => []]);

    $this->actingAs(new GenericUser(['id' => 5]))->get('/lynx/pages')->assertForbidden();
    $this->actingAs($admin)->get('/lynx/pages')->assertOk();
});

it('keeps the admin open to signed in users until someone has a role', function (): void {
    $this->actingAs(new GenericUser(['id' => 9]))->get('/lynx/pages')->assertOk();
});

it('describes the site only to MCP clients who may view pages or manage the schema', function (): void {
    $viewer = member(5, ['pages.view' => []]);
    $outsider = member(6, ['forms.view' => []]);

    LynxServer::actingAs($viewer)->tool(DescribeSchemaTool::class)->assertOk()->assertSee('course');
    LynxServer::actingAs($outsider)->tool(DescribeSchemaTool::class)->assertHasErrors(['describe_schema']);
});

it('lets MCP clients read pages only with pages.view', function (): void {
    $viewer = member(5, ['pages.view' => ['branches' => [lynx()->pages->get('/courses/')->id]]]);
    $outsider = member(6, ['pages.edit' => []]);

    LynxServer::actingAs($viewer)->tool(GetPageTool::class, ['page' => '/courses/alpha/'])->assertOk()->assertSee('alpha');
    LynxServer::actingAs($viewer)->tool(GetPageTool::class, ['page' => '/'])->assertHasErrors(['pages.view']);
    LynxServer::actingAs($outsider)->tool(FindPagesTool::class, ['selector' => 'template=course'])->assertHasErrors(['pages.view']);
});

it('applies the same rules to the built-in agent\'s read tools', function (): void {
    member(6, ['pages.edit' => []]);
    $tool = collect(ReadTool::all(app(AgentToolkit::class)))->first(fn (ReadTool $tool): bool => $tool->name() === 'get_page');

    $answer = lynx()->actingAs(Actor::ai(6), fn (): string => $tool->handle(new Request(['page' => '/courses/alpha/'])));

    expect($answer)->toContain('pages.view')->not->toContain('"template"');
});
