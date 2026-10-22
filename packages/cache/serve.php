<?php

declare(strict_types=1);

use LynxCms\Cache\Pages\PreBootServer;

/*
| Sends stored pages before Laravel boots (ADR-0064). Add one line to public/index.php, before
| bootstrap/app.php:
|
|     (require __DIR__.'/../vendor/lynxcms/cache/serve.php')(__DIR__.'/../bootstrap/cache/lynx-cache.php');
|
| The settings file is written by `php artisan lynx:page-cache --compile`; without it nothing
| changes and Laravel handles every request. The classes are loaded here, so the line works
| before or after the Composer autoloader.
*/
return static function (string $compiledSettings): void {
    $config = is_file($compiledSettings) ? require $compiledSettings : null;

    if (! is_array($config)) {
        return;
    }

    foreach (['CacheAddress', 'CachedPage', 'RequestRules', 'PreBootAnswer', 'PreBootServer'] as $class) {
        if (! class_exists('LynxCms\\Cache\\Pages\\'.$class, false)) {
            require_once __DIR__.'/src/Pages/'.$class.'.php';
        }
    }

    new PreBootServer($config)->answer($_SERVER, $_COOKIE)?->send();
};
