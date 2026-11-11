<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Support\Value;

/**
 * The site language each user last chose (lynx_language_id, set by languages.set_current and read
 * by DetectLocale), on the table of the app's user model.
 * The first migration adds it to a table named users; apps whose users live elsewhere get it here.
 */
return new class extends Migration
{
    private const string COLUMN = 'lynx_language_id';

    private const string DEFAULT_TABLE = 'users';

    public function up(): void
    {
        $table = $this->usersTable();

        if ($table !== null && Schema::hasTable($table) && ! Schema::hasColumn($table, self::COLUMN)) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger(self::COLUMN)->nullable();
            });
        }
    }

    public function down(): void
    {
        $table = $this->usersTable();

        if ($table !== null && Schema::hasTable($table) && Schema::hasColumn($table, self::COLUMN)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(self::COLUMN));
        }
    }

    /** The user model's table, or null when it is users (the first migration's) or there is no Eloquent model. */
    private function usersTable(): ?string
    {
        $model = Value::string(config('auth.providers.users.model'));

        if (! is_subclass_of($model, Model::class)) {
            return null;
        }

        $table = (new $model)->getTable();

        return $table === self::DEFAULT_TABLE ? null : $table;
    }
};
