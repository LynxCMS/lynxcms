<?php

declare(strict_types=1);

namespace LynxCms\Tests;

use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\PasskeysServiceProvider;
use Override;

/**
 * Providers in the order an app's package discovery loads them: alphabetically by package, so
 * laravel/fortify and laravel/passkeys register before lynxcms/admin. TestCase registers the admin
 * first, which hides settings Fortify copies while it registers.
 */
abstract class ProviderOrderTestCase extends TestCase
{
    /** @return list<class-string> */
    #[Override]
    protected function getPackageProviders($app): array
    {
        $first = [FortifyServiceProvider::class, PasskeysServiceProvider::class];

        return [...$first, ...array_values(array_diff(parent::getPackageProviders($app), $first))];
    }
}
