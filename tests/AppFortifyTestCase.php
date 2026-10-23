<?php

declare(strict_types=1);

namespace LynxCms\Tests;

use Laravel\Fortify\Features;
use Override;

/**
 * An app that signs users in with its own Fortify (registration, password reset and two-factor
 * authentication at the site's root), as a members area does. Lynx's admin then uses the app's
 * sign-in and keeps its invitations and account screen (ADR-0074). Fortify reads this config while
 * its provider registers, so it is set before the providers register.
 */
abstract class AppFortifyTestCase extends TestCase
{
    /** Before the providers register, as the app's config/fortify.php would be. */
    #[Override]
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('lynx-admin.auth.enabled', 'app');
        $app['config']->set('fortify.prefix', '');
        $app['config']->set('fortify.home', '/account');
        $app['config']->set('fortify.views', true);
        $app['config']->set('fortify.features', [
            Features::registration(),
            Features::resetPasswords(),
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
        ]);
    }
}
