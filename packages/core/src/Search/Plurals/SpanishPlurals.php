<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Spanish plurals (ADR-0119). -es is dropped after a vowel and r, l, n or d (flores, árboles,
 * jóvenes, ciudades), -ces after a vowel gives -z (luces); other -es plurals come from singulars in
 * -e (noches, padres, dulces), so they drop the s. Accents that move are folded away later.
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @api
 */
class SpanishPlurals extends EndingRules
{
    private const array VOWELS = ['a', 'e', 'i', 'o', 'u', 'á', 'é', 'í', 'ó', 'ú'];

    private const array ENDINGS = [
        'iones' => 'ión', 'ojes' => 'oj', 'yes' => 'y', 'eses' => 'és', 'uses' => 'ús', 'lles' => 'lle',
        'is' => null, 'us' => null,
        'es' => 'e', 's' => '',
    ];

    private const array INVARIABLE = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'crisis', 'análisis', 'tesis', 'virus', 'paraguas'];

    private const array IRREGULAR = ['países' => 'país', 'dioses' => 'dios'];

    protected function endings(): array
    {
        $endings = self::ENDINGS;

        foreach (self::VOWELS as $vowel) {
            foreach (['r', 'l', 'n', 'd'] as $consonant) {
                $endings[$vowel.$consonant.'es'] = $vowel.$consonant;
            }

            $endings[$vowel.'ces'] = $vowel.'z';
        }

        return $endings;
    }

    protected function exceptions(): array
    {
        return self::IRREGULAR + array_combine(self::INVARIABLE, self::INVARIABLE);
    }
}
