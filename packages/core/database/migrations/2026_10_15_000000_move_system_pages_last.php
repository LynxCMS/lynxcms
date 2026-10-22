<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * System pages (a module's branch such as /_menus/, the trash) now stay after their siblings, the
 * trash last, so the admin can hide them without shifting positions. Reorders existing siblings once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $trash = DB::table(Tables::PAGES)->join(Tables::TEMPLATES, Tables::TEMPLATES.'.id', '=', Tables::PAGES.'.template_id')
            ->where(Tables::TEMPLATES.'.name', 'trash')->value(Tables::PAGES.'.id');
        $isTrash = fn (object $row): bool => $trash !== null && Value::int($row->id) === Value::int($trash);
        $parents = DB::table(Tables::PAGES)->whereNotNull('parent_id')->whereNull('draft_of')->get(['parent_id', 'status'])
            ->filter(fn (object $row): bool => (Value::int($row->status) & PageStatus::SYSTEM) !== 0)
            ->pluck('parent_id')->unique();

        foreach ($parents as $parent) {
            $rows = DB::table(Tables::PAGES)->where('parent_id', $parent)->orderBy('sort')->orderBy('id')->get(['id', 'status']);
            $isSystem = fn (object $row): bool => (Value::int($row->status) & PageStatus::SYSTEM) !== 0;
            $ordered = [
                ...$rows->reject($isSystem)->pluck('id')->all(),
                ...$rows->filter($isSystem)->reject($isTrash)->pluck('id')->all(),
                ...$rows->filter($isTrash)->pluck('id')->all(),
            ];

            foreach ($ordered as $sort => $id) {
                DB::table(Tables::PAGES)->where('id', $id)->update(['sort' => $sort]);
            }
        }
    }

    public function down(): void
    {
        // Sort order only: nothing to undo.
    }
};
