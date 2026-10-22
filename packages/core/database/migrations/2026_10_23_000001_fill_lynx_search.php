<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use LynxCms\Core\Search\SearchIndex;

/**
 * Indexes the pages that exist before the search index (ADR-0054). Titles only, until templates
 * mark fields searchable.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SearchIndex::class)->rebuild();
    }

    public function down(): void
    {
        // The table goes with the previous migration's down().
    }
};
