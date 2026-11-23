<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Pages\References\PageReferenceIndex;
use LynxCms\Core\Support\Tables;

/**
 * Page references (ADR-0145): one row per reference a page's fields hold, filled for the pages
 * that exist. Rows filled here have no created_at, which means unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::PAGE_REFERENCES, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('linked_page_id');
            $table->unsignedBigInteger('field_id');
            $table->unsignedBigInteger('language_id')->nullable();
            $table->string('item_key', 26)->nullable();
            $table->string('kind', 16)->default('field');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index('linked_page_id');
            $table->index(['page_id', 'field_id']);
        });

        app(PageReferenceIndex::class)->rebuild();
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::PAGE_REFERENCES);
    }
};
