<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use Illuminate\Database\Schema\Blueprint;

/**
 * The table of an item field (ADR-0044): one row per item with language_id 0 (order, on/off,
 * depth, type, shared values), and one row per item and language holding its translations.
 *
 * @internal
 */
final class ItemTable
{
    public const int ITEM_ROW = 0;

    public static function define(Blueprint $table, string $index): void
    {
        $table->unsignedBigInteger('page_id');
        $table->char('item', 26);
        $table->unsignedBigInteger('language_id')->default(self::ITEM_ROW);
        $table->unsignedInteger('sort')->nullable();
        $table->boolean('enabled')->nullable();
        $table->unsignedSmallInteger('depth')->nullable();
        $table->string('type', 64)->nullable();
        $table->json('data')->nullable();
        $table->primary(['page_id', 'item', 'language_id'], "{$index}_pk");
        $table->index(['page_id', 'language_id'], "{$index}_lang");
    }
}
