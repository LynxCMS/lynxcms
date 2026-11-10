<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Finder\FinderTables;

/**
 * Saved finder searches (ADR-0111): a name, the search as FinderQuery::toArray() writes it, who saved
 * it ("user:5", a string since apps' user ids may be numbers or uuids) and whether everyone who may
 * use the finder sees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(FinderTables::SEARCHES, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('owner');
            $table->boolean('is_shared')->default(false);
            $table->json('query');
            $table->timestamps();
            $table->index(['owner', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(FinderTables::SEARCHES);
    }
};
