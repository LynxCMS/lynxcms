<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->pages->saveDraft('/courses/alpha/', ['title' => ['default' => 'Alpha in draft']]);
});

it('shows editors the draft at the page\'s URL, never cached or indexed', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/courses/alpha/?preview=draft')
        ->assertOk()
        ->assertSee('Alpha in draft')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('shows visitors the live page', function (): void {
    $this->get('/courses/alpha/?preview=draft')->assertOk()->assertSee('Alpha')->assertDontSee('Alpha in draft');
    $this->actingAs(new GenericUser(['id' => 1]))->get('/courses/alpha/')->assertDontSee('Alpha in draft');
});

it('shows users who may not edit the page the live page', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['viewer']]);

    $this->actingAs(new GenericUser(['id' => 5]))->get('/courses/alpha/?preview=draft')->assertDontSee('Alpha in draft');
});

it('previews the draft in every language', function (): void {
    lynx()->pages->saveDraft('/courses/alpha/', ['title' => ['pt' => 'Alfa em rascunho']]);
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->setLanguageName('pt', 'alfa', active: true);
    $alpha->save();

    $this->actingAs(new GenericUser(['id' => 1]))
        ->get('/pt/cursos/alfa/?preview=draft')
        ->assertSee('Alfa em rascunho');
});

it('keeps previewing drafts while the editor browses, until they exit', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get('/courses/alpha/?preview=draft')->assertSee('Alpha in draft')->assertSee('Previewing drafts');
    $this->get('/courses/alpha/')->assertSee('Alpha in draft');
    $this->get('/courses/alpha/?preview=live')->assertDontSee('Alpha in draft')->assertDontSee('Previewing drafts');
    $this->get('/courses/alpha/')->assertDontSee('Alpha in draft');
});
