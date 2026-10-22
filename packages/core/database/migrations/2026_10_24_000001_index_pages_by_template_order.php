<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Listings by template in the default order (template=x, sort by sort then id) read the first
 * rows from this index instead of sorting every page of the template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->index(['template_id', 'sort', 'id']);
            $table->dropIndex(['template_id']);
        });
    }

    public function down(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->index('template_id');
            $table->dropIndex(['template_id', 'sort', 'id']);
        });
    }
};
