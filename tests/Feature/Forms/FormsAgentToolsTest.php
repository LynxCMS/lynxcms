<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Ai\Tools\ReadTool;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Agents\GetSubmissionTool;
use LynxCms\Forms\Agents\ListSubmissionsTool;
use LynxCms\Forms\Models\Submission;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;
use Workbench\App\Models\User;

beforeEach(function (): void {
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => ['type' => 'live', 'cameras' => 4, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'consent' => true]]);
    $this->uuid = Submission::query()->sole()->uuid;
});

it('offers modules\' read tools to MCP clients and the agent', function (): void {
    $mcp = collect((fn (): array => $this->items)->call(LynxServer::tools()))->pluck('name')->all();
    $agent = array_map(fn (ReadTool $tool): string => $tool->name(), ReadTool::all(app(AgentToolkit::class)));

    expect($mcp)->toContain('list_submissions', 'get_submission')
        ->and($agent)->toContain('list_submissions', 'get_submission')
        ->and(app(ReadToolRegistry::class)->all())->toContain(ListSubmissionsTool::class, GetSubmissionTool::class);
});

it('lists submissions with a short summary', function (): void {
    LynxServer::tool(new ModuleReadTool(ListSubmissionsTool::class), ['status' => 'new'])
        ->assertOk()->assertSee($this->uuid)->assertSee('Ana Lima')->assertSee('quote');
});

it('reads one submission\'s answers', function (): void {
    LynxServer::tool(new ModuleReadTool(GetSubmissionTool::class), ['submission' => $this->uuid])
        ->assertOk()->assertSee('Cameras')->assertSee('Livestream')->assertSee('ana@example.com');
});

it('refuses clients whose user may not see submissions', function (): void {
    DB::table('users')->insert(['id' => 3, 'name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'x']);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['editor']]);

    LynxServer::actingAs(User::query()->find(3))->tool(new ModuleReadTool(ListSubmissionsTool::class), [])
        ->assertHasErrors(['forms.view']);
});
