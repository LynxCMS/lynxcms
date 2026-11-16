<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Turns a word into the form search matches it by (ADR-0119): a plural into its singular. The
 * word arrives lowercased, with its accents, holding letters and digits only. Register one per
 * locale with WordReducers.
 *
 * @api
 */
interface WordReducer
{
    /** The singular of $word, or $word unchanged. */
    public function reduce(string $word): string;
}
