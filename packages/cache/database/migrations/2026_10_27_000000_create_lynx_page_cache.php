<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Cache\PageCacheEntry;

/**
 * The page cache's index (ADR-0064): which stored address belongs to which page, so a change can
 * clear a page with its query variants, and expired files can be pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(PageCacheEntry::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->char('key_hash', 40)->unique();
            $table->string('host');
            $table->text('path');
            $table->text('query');
            $table->unsignedBigInteger('page_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->text('ancestor_ids');
            $table->unsignedBigInteger('language_id');
            $table->string('template')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(PageCacheEntry::TABLE);
    }
};
