<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Tables;

/**
 * Field and template labels may hold text per language, as JSON (ADR-0043), so they outgrow 255
 * characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([Tables::FIELDS, Tables::TEMPLATES] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->text('label')->change();
            });
        }
    }

    public function down(): void
    {
        foreach ([Tables::FIELDS, Tables::TEMPLATES] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('label')->change();
            });
        }
    }
};
