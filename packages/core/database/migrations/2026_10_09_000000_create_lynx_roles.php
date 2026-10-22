<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Roles, their grants (a permission, optionally limited to templates and branches of the tree) and
 * which host app users hold them. Users stay the host app's (ADR-0033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::ROLES, function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('label');
            $table->boolean('is_superuser')->default(false);
            $table->timestamps();
        });

        Schema::create(Tables::ROLE_PERMISSIONS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained(Tables::ROLES)->cascadeOnDelete();
            $table->string('permission', 64);
            $table->json('templates')->nullable();
            $table->json('branches')->nullable();
            $table->index(['role_id', 'permission']);
        });

        Schema::create(Tables::ROLE_USER, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained(Tables::ROLES)->cascadeOnDelete();
            $table->string('user_id');
            $table->string('assigned_by')->nullable();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::ROLE_USER);
        Schema::dropIfExists(Tables::ROLE_PERMISSIONS);
        Schema::dropIfExists(Tables::ROLES);
    }
};
