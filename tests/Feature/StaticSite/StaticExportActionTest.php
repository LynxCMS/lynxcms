<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\StaticSite\Actions\ExportStaticSite;
use LynxCms\StaticSite\Admin\Livewire\StaticExport;
use LynxCms\StaticSite\Export\ExportReport;

beforeEach(function (): void {
    $this->dist = testingPath('dist');
    File::deleteDirectory($this->dist);
    app()->usePublicPath(testingPath('public'));
    config(['lynx-static.path' => $this->dist, 'lynx-static.url' => 'https://static.example']);
    courseSite();
    createCourses('a', 'b');
});

afterEach(function (): void {
    File::deleteDirectory($this->dist);
    File::delete($this->dist.'.zip');
});

it('exports the site and reports what it wrote', function (): void {
    $result = lynx()->run(ExportStaticSite::class);

    expect($result->valueAs(ExportReport::class)->pages)->toBeGreaterThan(2)
        ->and(File::exists($this->dist.'/courses/a/index.html'))->toBeTrue()
        ->and($result->changes->all())->not->toBeEmpty();
});

it('counts the pages on a dry run and writes nothing', function (): void {
    $result = lynx()->run(ExportStaticSite::class, [], dryRun: true);

    expect($result->valueAs(ExportReport::class)->pages)->toBeGreaterThan(2)
        ->and(File::exists($this->dist))->toBeFalse()
        ->and(File::glob($this->dist.'.lynx-*'))->toBe([]);
});

it('refuses input that is not a yes or no for the zip', function (): void {
    expect(fn (): ActionResult => lynx()->run(ExportStaticSite::class, ['zip' => 'maybe']))->toThrow(ValidationException::class);
});

it('keeps the last export when a hook halts the next one', function (): void {
    lynx()->run(ExportStaticSite::class);
    $before = File::get($this->dist.'/courses/a/index.html');
    lynx()->hook(ExportStaticSite::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(ExportStaticSite::class))->toThrow(ActionHalted::class)
        ->and(File::get($this->dist.'/courses/a/index.html'))->toBe($before);
});

it('exports from the admin and offers the zip', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->get(route('lynx.admin.static'))->assertOk()->assertSeeLivewire(StaticExport::class)->assertSee('Not exported yet');

    Livewire::test(StaticExport::class)->call('export', true)->assertSee('Exported')->assertSee('Download the zip');

    $this->get(route('lynx.admin.static.download'))->assertOk()->assertDownload('static-site.zip');
})->skip(! class_exists(ZipArchive::class), 'needs ext-zip');

it('refuses the admin screen and the download to users without static.export', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.static'))->assertForbidden();
    $this->get(route('lynx.admin.static.download'))->assertNotFound();
    Livewire::test(StaticExport::class)->assertForbidden();
});
