<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Scheduled publishing per page and language (ADR-0036): one row per action ("publish" or
 * "unpublish") and language, run by lynx:schedule:run. A row that could not run keeps its error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::SCHEDULES, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('language_id');
            $table->string('action', 16);
            $table->timestamp('at');
            $table->string('created_by')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'language_id', 'action']);
            $table->index(['failed_at', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::SCHEDULES);
    }
};
