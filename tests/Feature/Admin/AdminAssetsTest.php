<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Admin\AdminAssets;

/*
 * Modules add their own stylesheets to the admin (ADR-0106): every page links them after the
 * admin's and before the site's, with a version that changes with the file, and the admin serves
 * them, so nothing is published.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->sheet = __DIR__.'/../../Fixtures/module/admin.css';
});

it('links a module\'s stylesheet after the admin\'s and before the site\'s', function (): void {
    config(['lynx-admin.stylesheets' => ['/site-theme.css']]);
    app(AdminAssets::class)->stylesheet('fixture', $this->sheet);

    $html = (string) $this->get(route('lynx.admin.pages'))->assertOk()->getContent();
    $admin = strpos($html, '/assets/admin.css');
    $module = strpos($html, 'data-module-stylesheet="fixture"');
    $site = strpos($html, '/site-theme.css');

    expect($admin)->toBeInt()->and($module)->toBeGreaterThan($admin)->and($site)->toBeGreaterThan($module)
        ->and($html)->toContain('/assets/modules/fixture.css?v='.filemtime($this->sheet));
});

it('serves a module\'s stylesheet as CSS', function (): void {
    app(AdminAssets::class)->stylesheet('fixture', $this->sheet);

    $response = $this->get(route('lynx.admin.assets.module', ['name' => 'fixture']));

    $response->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
    expect((string) $response->streamedContent())->toContain('.module-fixture');
});

it('answers 404 for a stylesheet nobody registered', function (): void {
    $this->get(route('lynx.admin.assets.module', ['name' => 'nobody']))->assertNotFound();
});

it('refuses a stylesheet name that is not a plain slug', function (): void {
    expect(fn () => app(AdminAssets::class)->stylesheet('../evil', $this->sheet))->toThrow(InvalidArgumentException::class);
});

it('puts a module\'s utilities below the admin\'s, so the admin\'s responsive classes keep winning', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/packages/admin/resources/css/module.css');

    // A sub-layer of utilities: the admin's own rules (hidden, md:flex) beat the module's copies.
    expect($css)->toContain('@import "tailwindcss/utilities.css" layer(utilities.module);')
        ->not->toContain('layer(utilities);');
});

it('starts modules from files the admin package ships', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 3).'/packages/admin/resources/css/module.css');
    preg_match_all('/@import "\.\/([^"]+)"/', $css, $local);

    expect($local[1])->toBe(['blatui-theme.css', 'lynx-theme.css']);

    foreach ($local[1] as $file) {
        expect(dirname(__DIR__, 3)."/packages/admin/resources/css/{$file}")->toBeFile();
    }
});
