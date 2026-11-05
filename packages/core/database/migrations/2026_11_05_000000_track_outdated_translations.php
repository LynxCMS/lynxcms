<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;

/**
 * A machine translation remembers the language of the text it was made from, and whether that
 * text has changed since (ADR-0102). Translations already made came from the default language,
 * the only source pages.translate used without being told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::FIELD_VALUE_META, function (Blueprint $table): void {
            $table->unsignedBigInteger('source_language_id')->nullable();
            $table->boolean('outdated')->default(false);
        });

        $default = DB::table(Tables::LANGUAGES)->exists() ? app(LanguageRegistry::class)->default()->id : null;

        if ($default !== null) {
            DB::table(Tables::FIELD_VALUE_META)->where('origin', 'machine')->update(['source_language_id' => $default]);
        }
    }

    /** Cannot bring back what it removes: which translations were outdated is lost. */
    public function down(): void
    {
        Schema::table(Tables::FIELD_VALUE_META, function (Blueprint $table): void {
            $table->dropColumn(['source_language_id', 'outdated']);
        });
    }
};
