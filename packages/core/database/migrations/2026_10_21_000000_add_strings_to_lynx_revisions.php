<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Revisions keep the page's own template strings too (ADR-0048); null for older revisions, which
 * restore without touching strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::REVISIONS, function (Blueprint $table): void {
            $table->longText('strings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(Tables::REVISIONS, function (Blueprint $table): void {
            $table->dropColumn('strings');
        });
    }
};
