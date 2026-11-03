<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/*
 * Every migration can be rolled back and run again, as migrate:rollback and migrate:refresh do in
 * a developer's app, whose batches may hold Lynx's migrations. Live sites restore a backup instead
 * (the upgrade guide). Tables a field creates (lynx_f_*) belong to no migration, so this runs
 * before anything is installed.
 */

/** @return list<string> Lynx's tables, without the host's prefix */
function lynxTables(): array
{
    $prefix = Schema::getConnection()->getTablePrefix();

    return collect(Schema::getTableListing(schemaQualified: false))
        ->map(fn (string $table): string => str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table)
        ->filter(fn (string $table): bool => str_starts_with($table, 'lynx_'))
        ->sort()
        ->values()
        ->all();
}

it('rolls every migration back, leaving no Lynx table, and runs them again', function (): void {
    $tables = lynxTables();
    expect($tables)->toContain('lynx_pages', 'lynx_fields', 'lynx_page_cache', 'lynx_form_submissions', 'lynx_menu_builds');

    expect(Artisan::call('migrate:reset', ['--force' => true]))->toBe(0)
        ->and(lynxTables())->toBe([])
        ->and(Schema::hasColumn('users', 'lynx_language_id'))->toBeFalse();

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0)
        ->and(lynxTables())->toBe($tables);
});

it('leaves passkeys and two-factor columns to the app when its own migrations made them', function (): void {
    $migration = require dirname(__DIR__, 3).'/packages/admin/database/migrations/2026_10_17_000000_add_lynx_sign_in_columns_to_users.php';
    $repository = app('migrator')->getRepository();
    $repository->log('2024_01_01_000000_create_passkeys_table', 1);
    $repository->log('2024_01_01_000001_add_two_factor_columns_to_users_table', 1);

    $migration->down();

    expect(Schema::hasTable('passkeys'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'two_factor_secret'))->toBeTrue();
});

it('removes passkeys and two-factor columns it added itself', function (): void {
    $migration = require dirname(__DIR__, 3).'/packages/admin/database/migrations/2026_10_17_000000_add_lynx_sign_in_columns_to_users.php';

    $migration->down();

    expect(Schema::hasTable('passkeys'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'two_factor_secret'))->toBeFalse();
});
