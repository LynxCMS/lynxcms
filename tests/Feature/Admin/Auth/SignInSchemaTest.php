<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/*
 * The admin's migration adds Fortify's two-factor columns and the passkeys table when the app has
 * not published those packages' own migrations (ADR-0041, ADR-0112). It copies their schema, so
 * this compares the two: a Fortify or passkeys release that changes its tables fails here.
 */

/**
 * The parts of the sign-in tables that matter to Fortify and passkeys: columns, indexes and keys,
 * without names a database picks for itself.
 *
 * @return array<string, mixed>
 */
function signInSchema(): array
{
    $twoFactor = array_values(array_filter(
        Schema::getColumns('users'),
        fn (array $column): bool => str_starts_with((string) $column['name'], 'two_factor_'),
    ));

    $describe = fn (array $rows, array $keys): array => collect($rows)
        ->map(fn (array $row): array => array_intersect_key($row, array_flip($keys)))
        ->sortBy(fn (array $row): string => (string) json_encode($row))
        ->values()
        ->all();

    return [
        'two_factor' => $describe($twoFactor, ['name', 'type_name', 'nullable']),
        'passkeys' => $describe(Schema::getColumns('passkeys'), ['name', 'type_name', 'nullable', 'auto_increment']),
        'passkey_indexes' => $describe(Schema::getIndexes('passkeys'), ['columns', 'unique', 'primary']),
        'passkey_keys' => $describe(Schema::getForeignKeys('passkeys'), ['columns', 'foreign_table', 'foreign_columns', 'on_delete']),
    ];
}

it('makes the same tables as the Fortify and passkeys migrations it stands in for', function (): void {
    $lynx = signInSchema();
    expect($lynx['two_factor'])->toHaveCount(3)->and($lynx['passkeys'])->not->toBeEmpty();

    $admin = require dirname(__DIR__, 4).'/packages/admin/database/migrations/2026_10_17_000000_add_lynx_sign_in_columns_to_users.php';
    $admin->down();
    (require dirname(__DIR__, 4).'/vendor/laravel/fortify/database/migrations/2014_10_12_200000_add_two_factor_columns_to_users_table.php')->up();
    (require dirname(__DIR__, 4).'/vendor/laravel/passkeys/database/migrations/2024_01_01_000000_create_passkeys_table.php')->up();

    expect(signInSchema())->toBe($lynx);
});
