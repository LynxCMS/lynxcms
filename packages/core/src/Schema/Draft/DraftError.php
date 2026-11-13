<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use RuntimeException;

/**
 * A draft that can't be built (ADR-0116): a type, setting or field that doesn't exist, or a change
 * schema sync can't make. The message names where in the draft, and suggests the closest name.
 *
 * @api
 */
final class DraftError extends RuntimeException
{
    /** Names this far apart (in edits) or closer count as a typo. */
    private const int MAX_TYPO_DISTANCE = 2;

    /**
     * " Did you mean [x]?" for the known name closest to the given one, or nothing when none is close.
     *
     * @param  list<string>  $known
     */
    public static function suggestion(string $given, array $known): string
    {
        $closest = null;
        $best = PHP_INT_MAX;

        foreach ($known as $name) {
            $distance = levenshtein($given, $name);

            if ($distance < $best) {
                [$closest, $best] = [$name, $distance];
            }
        }

        return $closest !== null && $best <= max(self::MAX_TYPO_DISTANCE, intdiv(strlen($given), 3)) ? " Did you mean [{$closest}]?" : '';
    }
}
