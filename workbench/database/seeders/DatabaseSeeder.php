<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use LynxCms\Core\Demo\DemoSite;
use LynxCms\Core\Pages\Page;

final class DatabaseSeeder extends Seeder
{
    /** 3 hand-written demo courses + 72 generated = 75, enough to page the admin tree (20 per page). */
    private const int GENERATED_COURSES = 72;

    public function run(DemoSite $demo): void
    {
        $demo->build(extraPages: self::GENERATED_COURSES);
        $this->topMenu();
        $this->footerMenu();
        $this->call(DemoUsersSeeder::class);
    }

    /** Hover dropdown in the site header: a page with its children, a section of links. */
    private function topMenu(): void
    {
        if (lynx()->menus()->page('top') !== null) {
            return;
        }

        lynx()->menus()->create('top', ['default' => 'Main navigation', 'pt' => 'Navegação principal']);
        lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/', 'label' => ['default' => 'Home', 'pt' => 'Início']]);
        lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/', 'children' => true, 'limit' => 8]);
        $resources = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['default' => 'Resources', 'pt' => 'Recursos']]);
        $resources = $resources instanceof Page ? (int) $resources->id : null;
        lynx()->menus()->saveItem(['menu' => 'top', 'parent' => $resources, 'kind' => 'link', 'label' => 'Laravel', 'url' => 'https://laravel.com', 'target' => '_blank', 'icon_after' => 'icon-external']);
        lynx()->menus()->saveItem(['menu' => 'top', 'parent' => $resources, 'kind' => 'link', 'label' => ['default' => 'Laravel News', 'pt' => 'Laravel News'], 'url' => 'https://laravel-news.com', 'target' => '_blank', 'icon_after' => 'icon-external', 'languages' => ['pt']]);
        lynx()->menus()->saveItem(['menu' => 'top', 'parent' => $resources, 'kind' => 'link', 'label' => ['default' => 'Email us', 'pt' => 'Contacte-nos'], 'url' => 'mailto:hello@example.com']);
    }

    /** Click accordion in the footer: sections holding pages and links. */
    private function footerMenu(): void
    {
        if (lynx()->menus()->page('footer') !== null) {
            return;
        }

        lynx()->menus()->create('footer', ['default' => 'Footer', 'pt' => 'Rodapé']);
        $learn = lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'section', 'label' => ['default' => 'Learn', 'pt' => 'Aprender']]);
        $learn = $learn instanceof Page ? (int) $learn->id : null;
        lynx()->menus()->saveItem(['menu' => 'footer', 'parent' => $learn, 'kind' => 'page', 'page' => '/courses/delivery-8/']);
        lynx()->menus()->saveItem(['menu' => 'footer', 'parent' => $learn, 'kind' => 'page', 'page' => '/courses/', 'label' => ['default' => 'All courses', 'pt' => 'Todos os cursos']]);
        $company = lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'section', 'label' => ['default' => 'Company', 'pt' => 'Empresa']]);
        $company = $company instanceof Page ? (int) $company->id : null;
        lynx()->menus()->saveItem(['menu' => 'footer', 'parent' => $company, 'kind' => 'link', 'label' => ['default' => 'About', 'pt' => 'Sobre'], 'url' => ['default' => '/#about', 'pt' => '/pt/#sobre']]);
        lynx()->menus()->saveItem(['menu' => 'footer', 'parent' => $company, 'kind' => 'link', 'label' => 'GitHub', 'url' => 'https://github.com', 'target' => '_blank', 'class' => 'is-external']);
    }
}
