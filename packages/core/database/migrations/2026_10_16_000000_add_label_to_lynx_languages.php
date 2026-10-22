<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * A short label for a language's tabs in the admin ("PT", "pt-BR"); null shows the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::LANGUAGES, function (Blueprint $table): void {
            $table->string('label', 32)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table(Tables::LANGUAGES, function (Blueprint $table): void {
            $table->dropColumn('label');
        });
    }
};
