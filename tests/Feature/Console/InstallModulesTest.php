<?php

declare(strict_types=1);

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use LynxCms\Core\Install\ModuleCatalog;

/*
 * lynx:install adds the free modules someone picks, with Composer, then applies their database
 * changes in a fresh process, which loads them.
 */

beforeEach(function (): void {
    // As on a site with only lynxcms/core.
    app()->instance(ModuleCatalog::class, new ModuleCatalog(fn (string $package): bool => $package === 'lynxcms/core', '0.1.56'));
    Process::fake();
    $this->composer = Mockery::mock(Composer::class);
    app()->instance(Composer::class, $this->composer);
});

afterEach(fn (): bool => putenv('SHELL_INTERACTIVE'));

it('requires the modules given with --modules, then migrates and installs in a new process', function (): void {
    $this->composer->shouldReceive('requirePackages')->once()->withArgs(fn (array $packages): bool => $packages === ['lynxcms/admin:0.1.56', 'lynxcms/menus:0.1.56'])->andReturn(true);

    $this->artisan('lynx:install', ['--modules' => 'admin, menus', '--no-interaction' => true])->assertSuccessful();

    Process::assertRan(fn ($process): bool => in_array('lynx:migrate', (array) $process->command, true));
    Process::assertRan(fn ($process): bool => in_array('lynx:install', (array) $process->command, true));
});

it('refuses a module it does not know, and requires nothing', function (): void {
    $this->composer->shouldNotReceive('requirePackages');

    $this->artisan('lynx:install', ['--modules' => 'admin,blog', '--no-interaction' => true])->assertFailed();

    Process::assertNothingRan();
});

it('asks which modules to add, with the admin chosen', function (): void {
    putenv('SHELL_INTERACTIVE=1');
    $this->composer->shouldReceive('requirePackages')->once()->withArgs(fn (array $packages): bool => $packages === ['lynxcms/admin:0.1.56', 'lynxcms/seo:0.1.56'])->andReturn(true);

    $this->artisan('lynx:install')
        ->expectsChoice('Which modules should Lynx add?', ['admin', 'seo'], app(ModuleCatalog::class)->missing())
        ->assertSuccessful();
});

it('installs only the core without --modules when it cannot ask', function (): void {
    $this->composer->shouldNotReceive('requirePackages');

    $this->artisan('lynx:install', ['--no-interaction' => true])->assertSuccessful();

    Process::assertNothingRan();
    expect(lynx()->languages->default()->code)->toBe('default');
});

it('says how to add the modules by hand when Composer fails', function (): void {
    $this->composer->shouldReceive('requirePackages')->once()->andReturn(false);

    $this->artisan('lynx:install', ['--modules' => 'admin', '--no-interaction' => true])
        ->expectsOutputToContain('composer require lynxcms/admin:0.1.56')
        ->assertFailed();

    Process::assertNothingRan();
});
