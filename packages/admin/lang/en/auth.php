<?php

declare(strict_types=1);

return [
    'email' => 'Email',
    'password' => 'Password',
    'repeat_password' => 'Repeat the password',
    'login' => [
        'title' => 'Sign in',
        'description' => 'Sign in to manage this site\'s content.',
        'passkey' => 'Sign in with a passkey',
        'or_email' => 'or with your email',
        'forgot' => 'Forgot your password?',
        'remember' => 'Keep me signed in',
        'submit' => 'Sign in',
    ],
    'confirm' => [
        'title' => 'Confirm your password',
        'description' => 'This is a protected area. Enter your password to continue.',
        'submit' => 'Confirm',
    ],
    'forgot' => [
        'title' => 'Forgot your password?',
        'description' => 'We will email you a link to choose a new one.',
        'submit' => 'Email a reset link',
        'back' => 'Back to sign in',
    ],
    'invitation' => [
        'welcome' => 'Welcome',
        'welcome_name' => 'Welcome, :name',
        'description' => 'Choose a password to finish setting up your account.',
        'password' => 'Choose a password',
        'submit' => 'Save password and sign in',
        'expired' => 'This invitation has expired or was already used. Ask for a new one.',
    ],
    'reset' => [
        'title' => 'Choose a new password',
        'password' => 'New password',
        'submit' => 'Save password',
    ],
    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Enter the code from your authenticator app, or one of your recovery codes.',
        'code' => 'Authentication code',
        'recovery_code' => 'Recovery code',
        'submit' => 'Continue',
        'use_code' => 'Use an authentication code',
        'use_recovery_code' => 'Use a recovery code',
    ],
];
