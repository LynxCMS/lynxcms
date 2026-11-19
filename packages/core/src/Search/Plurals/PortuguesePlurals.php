<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Portuguese plurals (ADR-0119), also typed without accents (estacoes, paes). For pt-PT and pt-BR
 * alike: plurals are the same, and the exceptions hold both spellings (ônibus and ónibus). -les and -ses only drop the s, since most such plurals come from
 * singulars in -le and -se (controles, frases); -eses gives -ês (meses, ingleses).
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @internal
 */
class PortuguesePlurals extends EndingRules
{
    private const array ENDINGS = [
        'ões' => 'ão', 'ães' => 'ão', 'ãos' => 'ão',
        // The same typed without accents, as visitors often do.
        'oes' => 'ao', 'aes' => 'ao',
        'ais' => 'al', 'éis' => 'el', 'eis' => 'il', 'óis' => 'ol', 'uis' => 'ul', 'is' => 'il',
        'ns' => 'm',
        'eses' => 'ês', 'res' => 'r', 'zes' => 'z',
        's' => '',
    ];

    private const array INVARIABLE = ['lápis', 'pires', 'vírus', 'ônibus', 'ónibus', 'oásis', 'atlas', 'tênis', 'ténis', 'cais', 'mais', 'grátis', 'íris', 'bônus', 'bónus', 'dois', 'depois', 'seis', 'após'];

    private const array IRREGULAR = [
        'mães' => 'mãe', 'maes' => 'mae', 'pais' => 'pai', 'reis' => 'rei', 'leis' => 'lei', 'bois' => 'boi',
        'países' => 'país', 'deuses' => 'deus', 'gases' => 'gás', 'males' => 'mal',
        'torres' => 'torre', 'padres' => 'padre', 'febres' => 'febre', 'livres' => 'livre',
        'pobres' => 'pobre', 'nobres' => 'nobre', 'alegres' => 'alegre',
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
