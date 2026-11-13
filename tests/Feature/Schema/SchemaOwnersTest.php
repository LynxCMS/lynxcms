<?php

declare(strict_types=1);

use LynxCms\Admin\Editor\SchemaOwners as AdminSchemaOwners;
use LynxCms\Core\Schema\SchemaOwners;

/*
 * Which templates and fields belong to Lynx or a module rather than to the site (ADR-0115). Core
 * keeps the registry, so the CLI and sites without the admin know it too; the admin reads it.
 */

it('owns the trash template as Lynx', function (): void {
    expect(app(SchemaOwners::class)->ownerOfTemplate('trash'))->toBe('Lynx');
});

it('leaves the page not found template to the site', function (): void {
    expect(app(SchemaOwners::class)->isSystemTemplate('not-found'))->toBeFalse();
});

it('has the modules register their schema in core', function (): void {
    $owners = app(SchemaOwners::class);

    expect($owners->ownerOfTemplate('menu-item'))->toBe('Menus')
        ->and($owners->ownerOfField('menu_page'))->toBe('Menus')
        ->and($owners->ownerOfTemplate('seo-settings'))->toBe('SEO')
        ->and($owners->ownerOfTemplate('sitemap-settings'))->toBe('Sitemap')
        ->and($owners->ownerOfTemplate('page-cache-settings'))->toBe('Page cache')
        ->and($owners->ownerOfTemplate('form'))->toBe('Forms');
});

it('keeps the first owner of a name', function (): void {
    app(SchemaOwners::class)->register('Other', ['menu-item']);

    expect(app(SchemaOwners::class)->ownerOfTemplate('menu-item'))->toBe('Menus');
});

it('shares the registry with the admin', function (): void {
    app(SchemaOwners::class)->register('Demo', ['course'], ['price']);
    app(AdminSchemaOwners::class)->register('Legacy', ['courses']);

    expect(app(AdminSchemaOwners::class)->ownerOfTemplate('course'))->toBe('Demo')
        ->and(app(AdminSchemaOwners::class)->isSystemField('price'))->toBeTrue()
        ->and(app(SchemaOwners::class)->ownerOfTemplate('courses'))->toBe('Legacy');
});
