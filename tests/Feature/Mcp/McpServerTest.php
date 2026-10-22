<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Lynx;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ActionTool;
use LynxCms\Mcp\Tools\DescribeSchemaTool;
use LynxCms\Mcp\Tools\FindPagesTool;
use LynxCms\Mcp\Tools\GetPageTool;

beforeEach(fn (): Lynx => installLynx());

it('exposes read tools and one tool per action', function (): void {
    $names = collect((fn (): array => $this->items)->call(LynxServer::tools()))->pluck('name')->all();

    expect($names)->toContain('describe_schema', 'find_pages', 'get_page', 'get_page_history', 'pages_save', 'languages_create', 'fields_create', 'pages_switch_template')
        ->and(count($names))->toBe(5 + count(app(ReadToolRegistry::class)->all()) + count(lynx()->actions()->tools()));
});

it('describes the schema', function (): void {
    LynxServer::tool(DescribeSchemaTool::class)
        ->assertOk()
        ->assertSee('default_language')
        ->assertSee('home');
});

it('builds a translated page from a blank install, dry-running first', function (): void {
    LynxServer::tool(new ActionTool(CreateLanguage::class), ['code' => 'pt', 'name' => 'Português', 'fallback' => 'default'])->assertOk();
    LynxServer::tool(new ActionTool(CreateField::class), ['name' => 'summary', 'type' => 'textarea', 'translatable' => true])->assertOk();
    LynxServer::tool(new ActionTool(CreateTemplate::class), ['name' => 'testimonial', 'fields' => [['name' => 'title', 'required' => true], 'summary']])->assertOk();

    $args = [
        'template' => 'testimonial',
        'parent' => '/',
        'names' => ['default' => 'ana', 'pt' => 'ana-pt'],
        'values' => ['title' => ['default' => 'Ana', 'pt' => 'Ana'], 'summary' => ['default' => 'Great course', 'pt' => 'Ótimo curso']],
    ];

    LynxServer::tool(new ActionTool(SavePage::class), $args + ['dry_run' => true])
        ->assertOk()
        ->assertSee('dry_run')
        ->assertSee('Create testimonial page ana');

    expect(lynx()->pages->get('/ana/'))->toBeNull();

    LynxServer::tool(new ActionTool(SavePage::class), $args)->assertOk();

    $page = lynx()->pages->get('/ana/');
    expect($page)->not->toBeNull()
        ->and($page->getLanguageValue('pt', 'summary'))->toBe('Ótimo curso')
        ->and(ActivityEvent::query()->where('actor_kind', 'mcp')->count())->toBe(4);
});

it('returns validation errors to the agent', function (): void {
    LynxServer::tool(new ActionTool(CreateField::class), ['name' => 'Bad Name', 'type' => 'text'])
        ->assertHasErrors();
});

it('refuses languages outside the allowlist', function (): void {
    lynx()->languages->add('pt', 'Português');
    config(['lynx.agents.allowed_languages' => ['default']]);

    LynxServer::tool(new ActionTool(SavePage::class), [
        'id' => lynx()->pages->get('/')->id,
        'values' => ['title' => ['pt' => 'Início']],
    ])->assertHasErrors(['This agent may not write in: pt.']);
});

it('finds and reads pages', function (): void {
    LynxServer::tool(FindPagesTool::class, ['selector' => 'template=home'])->assertOk()->assertSee('total');
    LynxServer::tool(GetPageTool::class, ['page' => '/'])->assertOk()->assertSee('home');
});
