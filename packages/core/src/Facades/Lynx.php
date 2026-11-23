<?php

declare(strict_types=1);

namespace LynxCms\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The methods of the Lynx service that lynx() returns, such as Lynx::run() and Lynx::hook(), called statically.
 *
 * @mixin \LynxCms\Core\Lynx
 *
 * @api
 */
final class Lynx extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LynxCms\Core\Lynx::class;
    }
}
