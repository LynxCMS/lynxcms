<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations waiting for the invited user to choose a password (ADR-0041), so the Users screen
 * can show them and send them again. A row goes once the user sets a password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lynx_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->unique();
            $table->string('email');
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lynx_invitations');
    }
};
