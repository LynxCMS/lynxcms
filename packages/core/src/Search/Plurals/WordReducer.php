<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Turns a word into the form search matches it by (ADR-0119): a plural into its singular, or, in
 * languages whose plurals change the end of the word in ways a rule cannot undo (Italian, German),
 * singular and plural into the stem they share. Index and queries are reduced alike, so either
 * works. The word arrives lowercased, with its accents, holding letters and digits only. Register
 * one per locale with WordReducers.
 *
 * @api
 */
interface WordReducer
{
    /** The singular (or shared stem) of $word, or $word unchanged. */
    public function reduce(string $word): string;
}
