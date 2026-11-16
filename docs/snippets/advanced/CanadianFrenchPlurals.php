<?php

declare(strict_types=1);

namespace App\Lynx;

use LynxCms\Core\Search\Plurals\FrenchPlurals;

// #region reducer
/**
 * Canadian French: Lynx's French rules, plus words common in Québec that they get wrong (they
 * would read autobus and campus as plurals).
 */
class CanadianFrenchPlurals extends FrenchPlurals
{
    protected function exceptions(): array
    {
        return ['autobus' => 'autobus', 'campus' => 'campus'] + parent::exceptions();
    }
}
// #endregion reducer
