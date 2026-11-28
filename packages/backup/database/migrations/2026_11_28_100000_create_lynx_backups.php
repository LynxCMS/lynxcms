<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Backup\BackupTables;

/**
 * Database backups (ADR-0158): why and by whom each was made, where its zip is, and how it ended.
 * The zips are files on disks; this table lets the admin list them without reading the disks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(BackupTables::BACKUPS, function (Blueprint $table): void {
            $table->id();
            $table->string('reason');
            $table->string('actor');
            $table->string('status', 16);
            $table->string('format', 16)->nullable();
            $table->json('disks')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('files')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(BackupTables::BACKUPS);
    }
};
