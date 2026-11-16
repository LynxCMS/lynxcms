<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Italian (ADR-0119): plurals change the last vowel (libro and libri, casa and case, cane and
 * cani), and -o and -e both give -i, so no rule finds the singular. Singular and plural are both
 * reduced to the stem they share instead (libr, cas, can); -chi and -ghi keep their hard sound
 * (amici and amico give amic, laghi and lago lag). Words ending in a consonant or an accented
 * vowel do not change (città, film). The cost: words that differ only in the last vowel meet
 * (casa and caso).
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @api
 */
class ItalianPlurals extends EndingRules
{
    private const array ENDINGS = [
        'chi' => 'c', 'ghi' => 'g', 'che' => 'c', 'ghe' => 'g',
        'cia' => 'c', 'gia' => 'g', 'cie' => 'c', 'gie' => 'g', 'io' => '',
        'a' => '', 'e' => '', 'i' => '', 'o' => '',
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
