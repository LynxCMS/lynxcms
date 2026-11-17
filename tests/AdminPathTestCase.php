<?php

declare(strict_types=1);

namespace LynxCms\Tests;

use Override;

/**
 * A site that serves the admin at /manage instead of /lynx, as LYNX_ADMIN_PREFIX=manage does.
 * The admin and Fortify read the path while their providers boot, so it is set before then.
 */
abstract class AdminPathTestCase extends TestCase
{
    #[Override]
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('lynx-admin.prefix', '/manage/');
    }
}
