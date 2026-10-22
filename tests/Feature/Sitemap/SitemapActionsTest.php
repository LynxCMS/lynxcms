<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Sitemap\Actions\ClearSitemapCache;
use LynxCms\Sitemap\Actions\InstallSitemap;
use LynxCms\Sitemap\Admin\Livewire\SitemapSettings;
use LynxCms\Sitemap\SitemapCache;
use LynxCms\Sitemap\SitemapSchema;

beforeEach(function (): void {
    courseSite();
    createCourses('a', 'b', 'c');
});

it('installs the settings page once', function (): void {
    lynx()->run(InstallSitemap::class);

    $settings = lynx()->settings(SitemapSchema::SETTINGS_TEMPLATE);

    expect($settings)->toBeInstanceOf(Page::class)
        ->and($settings->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        ->and(lynx()->run(InstallSitemap::class)->value)->toBeFalse();
});

it('rolls the install back when a hook halts it, and changes nothing on a dry run', function (): void {
    lynx()->run(InstallSitemap::class, [], dryRun: true);
    expect(lynx()->templates->find(SitemapSchema::SETTINGS_TEMPLATE))->toBeNull();

    lynx()->hook(InstallSitemap::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(InstallSitemap::class))->toThrow(ActionHalted::class)
        ->and(lynx()->templates->find(SitemapSchema::SETTINGS_TEMPLATE))->toBeNull();
});

it('clears the cache so the next request builds the sitemap again', function (): void {
    $version = app(SitemapCache::class)->version();

    $dry = lynx()->run(ClearSitemapCache::class, [], dryRun: true);
    expect(app(SitemapCache::class)->version())->toBe($version)
        ->and($dry->changes->all())->not->toBeEmpty();

    lynx()->run(ClearSitemapCache::class);
    expect(app(SitemapCache::class)->version())->not->toBe($version);
});

it('lists the parts from the command line and fills the cache', function (): void {
    config(['lynx-sitemap.per_file' => 4]);

    $this->artisan('lynx:sitemap', ['--warm' => true])
        ->expectsOutputToContain('sitemap-1.xml')
        ->expectsOutputToContain('5 pages, 3 parts')
        ->assertSuccessful();
});

it('sets the limit, grouping and excluded templates in the admin', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->get(route('lynx.admin.sitemap'))->assertOk()->assertSeeLivewire(SitemapSettings::class)->assertSee('/sitemap.xml');

    Livewire::test(SitemapSettings::class)
        ->assertSee('5 pages')
        ->set('perFile', '2')->set('groupBy', 'template')->set('exclude', ['home'])
        ->call('save')->assertHasNoErrors()
        ->assertSee('sitemap-course-1.xml')->assertDontSee('sitemap-home-1.xml');

    expect((string) $this->get('/sitemap.xml')->getContent())->toContain('sitemap-course-2.xml');

    Livewire::test(SitemapSettings::class)->set('perFile', '0')->call('save')->assertHasErrors('perFile');
    Livewire::test(SitemapSettings::class)->call('clearCache')->assertHasNoErrors();
});

it('refuses the admin screen to users without sitemap.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.sitemap'))->assertForbidden();
    Livewire::test(SitemapSettings::class)->assertForbidden();
});
