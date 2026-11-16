<?php

declare(strict_types=1);

use App\Lynx\CanadianFrenchPlurals;
use LynxCms\Core\Search\Plurals\WordReducers;

// #region register
// In a service provider's boot() method: fr-CA languages use it, other French ones keep Lynx's.
app(WordReducers::class)->register('fr-CA', new CanadianFrenchPlurals);
// #endregion register
