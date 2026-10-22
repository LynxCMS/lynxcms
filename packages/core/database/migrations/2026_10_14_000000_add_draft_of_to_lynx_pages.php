<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * A draft is a page row holding the next version of a live page's values (ADR-0039). It has no
 * parent, names or paths, and every listing of pages leaves it out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->unsignedBigInteger('draft_of')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->dropUnique(['draft_of']);
            $table->dropColumn('draft_of');
        });
    }
};
