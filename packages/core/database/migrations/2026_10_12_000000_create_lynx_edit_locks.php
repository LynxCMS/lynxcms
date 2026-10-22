<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/** Who is editing a page and until when (ADR-0037). An expired row is free to take. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::EDIT_LOCKS, function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id')->primary();
            $table->string('holder');
            $table->timestamp('acquired_at');
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::EDIT_LOCKS);
    }
};
