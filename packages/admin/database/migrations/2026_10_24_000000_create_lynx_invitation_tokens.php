<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitation tokens apart from the app's password reset tokens (ADR-0041), so a reset link can't
 * be used as a days-long invitation and the other way round. Tokens of pending invitations move
 * here, so links already sent keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lynx_invitation_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        $resets = (string) (config('auth.passwords.users.table') ?? 'password_reset_tokens');

        if (! Schema::hasTable($resets) || ! Schema::hasTable('lynx_invitations')) {
            return;
        }

        $pending = DB::table('lynx_invitations')->pluck('email')->map(fn (mixed $email): string => mb_strtolower((string) $email))->all();
        $rows = DB::table($resets)->whereIn('email', $pending)->get(['email', 'token', 'created_at']);

        foreach ($rows as $row) {
            DB::table('lynx_invitation_tokens')->insert((array) $row);
        }

        DB::table($resets)->whereIn('email', $pending)->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('lynx_invitation_tokens');
    }
};
