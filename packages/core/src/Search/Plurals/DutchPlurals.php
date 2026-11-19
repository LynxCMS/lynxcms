<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Dutch plurals (ADR-0119): -en with its spelling undone (katten gives kat, bomen boom, huizen
 * huis, brieven brief), -eren (kinderen), and -s after -el, -er, -em, -en and -je (tafels,
 * meisjes). Plurals whose vowel is short with a single consonant (dagen, wegen) are exceptions.
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @internal
 */
class DutchPlurals extends EndingRules
{
    private const array ENDINGS = [
        'eren' => '', 'zen' => 's', 'ven' => 'f', 'en' => '',
        'els' => 'el', 'ers' => 'er', 'ems' => 'em', 'ens' => 'en', 'jes' => 'je',
    ];

    private const array IRREGULAR = [
        'dagen' => 'dag', 'wegen' => 'weg', 'paden' => 'pad', 'goden' => 'god', 'gaten' => 'gat',
        'sloten' => 'slot', 'glazen' => 'glas', 'schepen' => 'schip', 'steden' => 'stad',
    ];

    public function reduce(string $word): string
    {
        $reduced = parent::reduce($word);

        if (isset($this->exceptions()[$word]) || ! str_ends_with($word, 'en') || $reduced !== substr($word, 0, -2)) {
            return $reduced;
        }

        return $this->respelled($reduced);
    }

    protected function endings(): array
    {
        return self::ENDINGS;
    }

    protected function exceptions(): array
    {
        return self::IRREGULAR;
    }

    /** Undoes the plural's spelling: a doubled consonant (katt) or a vowel written once (bom). */
    private function respelled(string $stem): string
    {
        if (preg_match('/([bcdfgklmnprst])\1$/', $stem) === 1) {
            return substr($stem, 0, -1);
        }

        return preg_match('/[^aeiou]([aeou])[bcdfgklmnprst]$/', $stem, $match) === 1
            ? substr($stem, 0, -1).$match[1].substr($stem, -1)
            : $stem;
    }
}
