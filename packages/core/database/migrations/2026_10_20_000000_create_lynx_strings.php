<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Edited template strings (ADR-0047): one row per page, key and language, only where an editor
 * changed the text the template's code gives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::STRINGS, function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id');
            $table->string('key', 128);
            $table->unsignedBigInteger('language_id');
            $table->text('value');
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['page_id', 'key', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::STRINGS);
    }
};
