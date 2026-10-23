<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
 * The site's own sign-in, for members and staff alike. Because this file exists, Lynx's admin
 * uses these screens too (LYNX_ADMIN_AUTH=auto), and keeps its invitations for staff.
 */

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/account',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'views' => true,
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        // Staff turn it on under Account security in the admin
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
