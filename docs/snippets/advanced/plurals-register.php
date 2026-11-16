<?php

declare(strict_types=1);

use App\Lynx\SwedishPlurals;
use LynxCms\Core\Search\Plurals\WordReducers;

// #region register
// In a service provider's boot() method:
app(WordReducers::class)->register('sv', new SwedishPlurals);
// #endregion register
