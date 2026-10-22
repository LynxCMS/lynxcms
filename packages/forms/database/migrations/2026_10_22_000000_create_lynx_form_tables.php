<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Forms\FormsTables;

/**
 * Form submissions and their files (ADR-0052). No IP address is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(FormsTables::SUBMISSIONS, function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('form', 64);
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->json('data');
            $table->json('fields');
            $table->string('status', 16)->default('new');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('read_by')->nullable();
            $table->timestamps();
            $table->index(['form', 'status', 'created_at']);
        });

        Schema::create(FormsTables::FILES, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained(FormsTables::SUBMISSIONS)->cascadeOnDelete();
            $table->string('field', 64);
            $table->string('path');
            $table->string('name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(FormsTables::FILES);
        Schema::dropIfExists(FormsTables::SUBMISSIONS);
    }
};
