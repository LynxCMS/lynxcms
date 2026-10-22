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

    public function down(): void
    {
        // The app may rely on these too: they stay.
    }
};
