<?php

declare(strict_types=1);

namespace App\Lynx;

use LynxCms\Core\Search\Plurals\WordReducer;

// #region reducer
/**
 * Swedish plurals for search: bilar finds bil, and flickor finds flicka. The word arrives
 * lowercased, with its accents; return its singular, or the word unchanged.
 */
final class SwedishPlurals implements WordReducer
{
    public function reduce(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'or') => substr($word, 0, -2).'a',
            str_ends_with($word, 'ar'), str_ends_with($word, 'er') => substr($word, 0, -2),
            default => $word,
        };
    }
}
// #endregion reducer
