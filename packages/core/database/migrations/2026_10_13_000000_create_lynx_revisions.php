<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * A page's field values in every language after each save that changed them (ADR-0038), the
 * newest lynx.revisions.keep per page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::REVISIONS, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedInteger('revision');
            $table->longText('values');
            $table->string('created_by')->nullable();
            $table->timestamp('created_at');
            $table->index(['page_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::REVISIONS);
    }
};
