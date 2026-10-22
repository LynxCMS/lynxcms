<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * A counter raised by every write to a page row, so editors can tell when a page changed after
 * they loaded it (pages.save "expected_revision"). Timestamps are not precise enough for that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table(Tables::PAGES, function (Blueprint $table): void {
            $table->dropColumn('revision');
        });
    }
};
