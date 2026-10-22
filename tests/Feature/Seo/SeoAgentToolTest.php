<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Agents\InspectSeoTool;
use LynxCms\Seo\SeoSchema;

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'names' => ['pt' => 'alfa'], 'values' => [SeoSchema::DESCRIPTION => ['default' => 'About alpha.', 'pt' => 'Sobre alfa.']]]);
});

it('gives agents a page\'s SEO in a language, by path or id', function (): void {
    LynxServer::tool(new ModuleReadTool(InspectSeoTool::class), ['page' => '/courses/alpha', 'language' => 'pt'])
        ->assertOk()->assertSee('Sobre alfa.')->assertSee('http://localhost/pt/cursos/alfa');

    LynxServer::tool(new ModuleReadTool(InspectSeoTool::class), ['page' => (string) lynx()->pages->get('/courses/alpha')->id])
        ->assertOk()->assertSee('About alpha.');
});

it('says when the page or language does not exist', function (): void {
    LynxServer::tool(new ModuleReadTool(InspectSeoTool::class), ['page' => '/nope'])->assertSee('There is no such page.');
    LynxServer::tool(new ModuleReadTool(InspectSeoTool::class), ['page' => '/courses/alpha', 'language' => 'xx'])->assertSee('There is no language [xx].');
});
