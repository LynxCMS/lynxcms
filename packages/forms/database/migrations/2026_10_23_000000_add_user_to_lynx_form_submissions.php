<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Forms\FormsTables;

/**
 * The account that sent a submission, when a signed-in visitor sent it (a member answering a
 * survey). A string, like read_by, since apps' user ids may be numbers or uuids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(FormsTables::SUBMISSIONS, function (Blueprint $table): void {
            $table->string('user_id')->nullable()->after('language_id');
            $table->index(['form', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table(FormsTables::SUBMISSIONS, function (Blueprint $table): void {
            $table->dropIndex(['form', 'user_id']);
            $table->dropColumn('user_id');
        });
    }
};
