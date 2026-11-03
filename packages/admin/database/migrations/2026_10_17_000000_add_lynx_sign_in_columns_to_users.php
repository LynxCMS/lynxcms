<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkeys;

/**
 * What the admin's two-factor authentication and passkeys need on the app's users (ADR-0041),
 * as Fortify's own migrations add them. Skips what the app already has, so apps that published
 * Fortify's migrations are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = (new (Passkeys::userModel()))->getTable();

        if (! Schema::hasTable($users)) {
            return;
        }

        if (! Schema::hasColumn($users, 'two_factor_secret')) {
            Schema::table($users, function (Blueprint $table): void {
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
            });
        }

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) use ($users): void {
                $table->id();
                $table->foreignId('user_id')->constrained($users)->cascadeOnDelete();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }
    }

    /**
     * Removes what up() added, unless the app's own Passkeys or Fortify migration made it: that
     * migration owns the table or columns, and drops them on its own rollback. Left behind, the
     * passkeys table's key on users would stop the app's users migration from rolling back.
     */
    public function down(): void
    {
        $users = (new (Passkeys::userModel()))->getTable();

        if (! $this->appMigrationRan('create_passkeys_table')) {
            Schema::dropIfExists('passkeys');
        }

        if (! $this->appMigrationRan('two_factor_columns') && Schema::hasTable($users) && Schema::hasColumn($users, 'two_factor_secret')) {
            Schema::table($users, function (Blueprint $table): void {
                $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
            });
        }
    }

    private function appMigrationRan(string $name): bool
    {
        foreach (app('migrator')->getRepository()->getRan() as $migration) {
            if (str_contains((string) $migration, $name)) {
                return true;
            }
        }

        return false;
    }
};
