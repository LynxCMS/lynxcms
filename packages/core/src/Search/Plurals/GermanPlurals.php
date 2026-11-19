<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * German (ADR-0119): plurals add -e, -er, -en or -n, and often an umlaut (Tag and Tage, Haus and
 * Häuser, Blume and Blumen). The ending a singular already has (Blume) cannot be told from one a
 * plural added, so both are reduced to the stem they share (tag, häus, blum); folding then drops
 * the umlaut (haus, apfel). -s comes off loanwords in -os and -ys (Autos). Words that differ only in
 * these endings meet (Lehre and Lehrer).
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @internal
 */
class GermanPlurals extends EndingRules
{
    private const array ENDINGS = [
        'innen' => 'in', 'ern' => '', 'eln' => 'el', 'en' => '', 'er' => '', 'e' => '',
        'os' => 'o', 'ys' => 'y', 'ss' => null,
    ];

    protected function endings(): array
    {
        return self::ENDINGS;
    }

    protected function exceptions(): array
    {
        return [];
    }
}
