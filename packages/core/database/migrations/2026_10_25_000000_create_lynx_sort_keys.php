<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Indexes\SortKeys;
use LynxCms\Core\Support\Tables;

/**
 * Sort keys (ADR-0061): the value each page shows per field and language, filled for the pages
 * that exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::SORT_KEYS, function (Blueprint $table): void {
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('field_id');
            $table->unsignedBigInteger('language_id');
            $table->string('value', SortKeys::MAX_LENGTH)->nullable();
            $table->primary(['page_id', 'field_id', 'language_id']);
            $table->index(['field_id', 'language_id', 'value', 'page_id']);
        });

        app(SortKeys::class)->rebuild();
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::SORT_KEYS);
    }
};
