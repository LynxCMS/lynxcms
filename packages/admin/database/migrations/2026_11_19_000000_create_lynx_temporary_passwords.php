<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users signing in with a temporary password a superuser gave them (ADR-0126): until they choose
 * their own, the admin asks for it, and after expires_at the temporary one no longer signs in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lynx_temporary_passwords', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lynx_temporary_passwords');
    }
};
