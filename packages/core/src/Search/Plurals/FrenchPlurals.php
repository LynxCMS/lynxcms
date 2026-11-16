<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * French plurals (ADR-0119): -eaux gives -eau, -aux gives -al, -x after u is dropped, and so is -s.
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @api
 */
class FrenchPlurals extends EndingRules
{
    private const array ENDINGS = [
        'eaux' => 'eau', 'aux' => 'al', 'ux' => 'u',
        's' => '',
    ];

    private const array INVARIABLE = ['mais', 'fois', 'mois', 'cours', 'vers', 'faux', 'taux', 'repas', 'souris', 'temps', 'corps', 'pays'];

    private const array IRREGULAR = [
        'travaux' => 'travail', 'vitraux' => 'vitrail', 'émaux' => 'émail', 'coraux' => 'corail',
    ];

    protected function endings(): array
    {
        return self::ENDINGS;
    }

    protected function exceptions(): array
    {
        return self::IRREGULAR + array_combine(self::INVARIABLE, self::INVARIABLE);
    }
}
