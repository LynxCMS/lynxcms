<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * One row per branch in the trash (its top page): where it was, its names and ancestors there,
 * so pages.restore can put it back and permissions still follow that place, and who trashed it
 * when (ADR-0035).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::TRASH, function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id')->primary();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedInteger('sort');
            $table->json('names');
            // Ancestors at the original place, for branch limited permissions inside the trash.
            $table->json('ancestors');
            $table->string('trashed_by')->nullable();
            $table->timestamp('trashed_at');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::TRASH);
    }
};
