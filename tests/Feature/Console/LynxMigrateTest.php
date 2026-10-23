<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * Installing a module with Composer runs package:discover, but never the module's migrations.
 * Lynx then names the module that needs database changes, and lynx:migrate applies them once
 * the user says the database is backed up.
 */

beforeEach(function (): void {
    app(Migrator::class)->path(dirname(__DIR__, 2).'/Fixtures/module/database/migrations');
});

/** What a listener writes after the named command finishes. */
function outputAfter(string $command): string
{
    $output = new BufferedOutput;
    event(new CommandFinished($command, new ArrayInput([]), $output, 0));

    return $output->fetch();
}

it('lists pending Lynx migrations by package and applies them once the backup is confirmed', function (): void {
    $this->artisan('lynx:migrate')
        ->expectsOutputToContain('lynxcms/fixture')
        ->expectsOutputToContain('2099_01_01_000000_create_lynx_fixture_notes')
        ->expectsConfirmation('Back up your database before these changes. Is it backed up?', 'yes')
        ->assertSuccessful();

    expect(Schema::hasTable('lynx_fixture_notes'))->toBeTrue();
});

it('changes nothing when the backup is not confirmed', function (): void {
    $this->artisan('lynx:migrate')
        ->expectsConfirmation('Back up your database before these changes. Is it backed up?', 'no')
        ->assertFailed();

    expect(Schema::hasTable('lynx_fixture_notes'))->toBeFalse();
});

it('skips the question with --force', function (): void {
    $this->artisan('lynx:migrate', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('lynx_fixture_notes'))->toBeTrue();
});

it('says so when nothing is pending', function (): void {
    $this->artisan('lynx:migrate', ['--force' => true]);

    $this->artisan('lynx:migrate')->expectsOutputToContain('Nothing to migrate')->assertSuccessful();
});

it('warns after package:discover when a Lynx package has pending migrations', function (): void {
    expect(outputAfter('package:discover'))
        ->toContain('lynxcms/fixture')
        ->toContain('php artisan lynx:migrate');
});

it('stays quiet after other commands', function (): void {
    expect(outputAfter('route:list'))->toBe('');
});

it('stays quiet after package:discover when nothing is pending', function (): void {
    $this->artisan('lynx:migrate', ['--force' => true]);

    expect(outputAfter('package:discover'))->toBe('');
});

it('stays quiet after package:discover when the database cannot be reached', function (): void {
    $default = config('database.default');
    config(['database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent/lynx.sqlite'], 'database.default' => 'unreachable']);

    try {
        expect(outputAfter('package:discover'))->toBe('');
    } finally {
        config(['database.default' => $default]);
    }
});
