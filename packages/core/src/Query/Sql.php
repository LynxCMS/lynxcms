<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * The one place where SQL assembled at runtime is marked as trusted for Laravel's raw methods,
 * which only accept literal strings. Callers may only pass SQL built from grammar-wrapped
 * identifiers, whitelisted operators and integer ids; user values always go through bindings.
 *
 * @internal
 */
final class Sql
{
    /**
     * @return literal-string
     */
    public static function trusted(string $sql): string
    {
        /** @var literal-string $sql */
        return $sql;
    }

    public static function raw(string $sql): Expression
    {
        return DB::raw(self::trusted($sql));
    }
}
