<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;

if (! function_exists('lynx')) {
    /**
     * The Lynx API root: lynx()->pages, lynx()->languages, lynx()->fields, lynx()->templates.
     */
    function lynx(): Lynx
    {
        return app(Lynx::class);
    }
}
