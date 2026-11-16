<?php

declare(strict_types=1);

namespace App\Lynx;

use LynxCms\Core\Search\Plurals\WordReducer;

// #region reducer
/**
 * Dutch plurals for search: boeken finds boek, and huizen finds huis. The word arrives lowercased,
 * with its accents; return its singular, or the word unchanged.
 */
final class DutchPlurals implements WordReducer
{
    public function reduce(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'zen') => substr($word, 0, -3).'s',
            str_ends_with($word, 'en') => substr($word, 0, -2),
            str_ends_with($word, 's') => substr($word, 0, -1),
            default => $word,
        };
    }
}
// #endregion reducer
