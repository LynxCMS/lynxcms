<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Cache\FragmentEntry;

/**
 * The fragment cache's index (ADR-0064): which fragments are stored, so content changes clear
 * the ones depending on them and the admin can list them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(FragmentEntry::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->char('key_hash', 40)->unique();
            $table->string('cache_key');
            $table->string('name')->index();
            $table->string('language');
            $table->string('audience');
            $table->text('vary');
            $table->text('depends_on');
            $table->unsignedInteger('size');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(FragmentEntry::TABLE);
    }
};
