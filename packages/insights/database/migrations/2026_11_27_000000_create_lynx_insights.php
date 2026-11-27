<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Insights\InsightsTables;

/**
 * Insight reports and their insights (ADR-0155). An insight keeps its plan (action calls), the pages
 * it is about with the time each was last changed (its basis), and what an editor decided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(InsightsTables::REPORTS, function (Blueprint $table): void {
            $table->id();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('failures');
            $table->string('created_by');
            $table->timestamps();
        });

        Schema::create(InsightsTables::INSIGHTS, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained(InsightsTables::REPORTS)->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('fingerprint', 40);
            $table->string('kind', 32);
            $table->string('title');
            $table->text('reason');
            $table->json('pages');
            $table->json('evidence');
            $table->json('plan');
            $table->json('basis');
            $table->float('weight')->default(0);
            $table->string('state', 16);
            $table->unsignedBigInteger('carried_from')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['fingerprint', 'state']);
            $table->index(['report_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(InsightsTables::INSIGHTS);
        Schema::dropIfExists(InsightsTables::REPORTS);
    }
};
