<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * English plurals (ADR-0119). -es is dropped after ss, x, zz, ch and sh only; other -es plurals
 * come from singulars in -e (cases, sizes), so they drop the s.
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @api
 */
class EnglishPlurals extends EndingRules
{
    private const array ENDINGS = [
        'ies' => 'y', 'sses' => 'ss', 'xes' => 'x', 'zzes' => 'z', 'ches' => 'ch', 'shes' => 'sh',
        'ss' => null, 'us' => null, 'is' => null, 'ous' => null,
        's' => '',
    ];

    private const array INVARIABLE = ['series', 'species', 'news', 'does', 'always', 'perhaps', 'whereas', 'lens'];

    private const array IRREGULAR = [
        'movies' => 'movie', 'cookies' => 'cookie', 'calories' => 'calorie', 'zombies' => 'zombie',
        'ties' => 'tie', 'pies' => 'pie', 'lies' => 'lie', 'dies' => 'die',
        'caches' => 'cache', 'niches' => 'niche', 'headaches' => 'headache',
        'heroes' => 'hero', 'potatoes' => 'potato', 'tomatoes' => 'tomato', 'echoes' => 'echo',
        'buses' => 'bus', 'gases' => 'gas',
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
