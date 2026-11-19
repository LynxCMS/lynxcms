<?php

declare(strict_types=1);

namespace LynxCms\Admin\Doctor;

use LynxCms\Admin\Auth\TwoFactorRequirement;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;

/**
 * The admin requires two-factor authentication, but nobody can set it up (ADR-0128): the
 * requirement then does nothing, so the site runs without it.
 *
 * @internal
 */
final class TwoFactorAvailable implements Check
{
    public function key(): string
    {
        return 'two-factor';
    }

    public function label(): string
    {
        return 'Two-factor authentication can be set up when the admin requires it';
    }

    public function run(): array
    {
        return ! TwoFactorRequirement::isRequired() || TwoFactorRequirement::canBeSetUp() ? [] : [new Finding(
            Severity::Warning,
            'The admin requires two-factor authentication, but it cannot be set up, so nobody is asked for it.',
            'Add Fortify\'s TwoFactorAuthenticatable trait to your user model. If config/fortify.php exists, turn on Features::twoFactorAuthentication() there. Or set LYNX_ADMIN_REQUIRE_TWO_FACTOR=false.',
        )];
    }
}
