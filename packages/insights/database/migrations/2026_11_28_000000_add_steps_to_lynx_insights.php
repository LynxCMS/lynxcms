<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Insights\InsightsTables;

/**
 * The outcome of each step of an insight's plan (ADR-0157): pending, done or failed with its error,
 * in the order of the plan. Null until the insight is first approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(InsightsTables::INSIGHTS, function (Blueprint $table): void {
            $table->json('steps')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(InsightsTables::INSIGHTS, function (Blueprint $table): void {
            $table->dropColumn('steps');
        });
    }
};
