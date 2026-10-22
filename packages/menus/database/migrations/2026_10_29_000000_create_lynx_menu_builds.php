<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Menus\Models\MenuBuild;
use LynxCms\Menus\Models\MenuBuildPage;

/**
 * Stored menus (ADR-0068): each menu built per language for the default visitor, and the pages each
 * build used, so a change to one of them marks the build stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(MenuBuild::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('menu_id')->index();
            $table->string('menu_name', 64);
            $table->unsignedBigInteger('language_id');
            $table->longText('data');
            $table->timestamp('built_at');
            $table->unique(['menu_name', 'language_id']);
        });

        Schema::create(MenuBuildPage::TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('page_id');
            // "page": the build shows or depends on this page; "children": it lists this page's children.
            $table->string('kind', 8);
            $table->primary(['menu_id', 'page_id', 'kind']);
            $table->index(['page_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(MenuBuildPage::TABLE);
        Schema::dropIfExists(MenuBuild::TABLE);
    }
};
