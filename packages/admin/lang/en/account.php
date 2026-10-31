<?php

declare(strict_types=1);

return [
    'menu' => [
        'appearance' => 'Appearance',
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
        'language' => 'Language',
        'translation_hints' => 'Translation hints',
        'security' => 'Security',
    ],
    'security' => [
        'title' => 'Account security',
        'description' => 'Your password, two-factor authentication and passkeys.',
    ],
    'password' => [
        'heading' => 'Password',
        'current' => 'Current password',
        'new' => 'New password',
        'repeat' => 'Repeat the new password',
        'submit' => 'Change password',
        'changed' => 'Password changed.',
    ],
    'two_factor' => [
        'heading' => 'Two-factor authentication',
        'description' => 'After your password, signing in asks for a code from an authenticator app on your phone.',
        'scan' => 'Scan the QR code with your authenticator app, or enter the setup key. Then type the code it shows.',
        'setup_key' => 'Setup key:',
        'code' => 'Code',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
        'invalid_code' => 'That code is not valid. Try the newest one from your app.',
        'on' => 'On',
        'on_description' => 'Signing in asks for a code.',
        'recovery_heading' => 'Recovery codes',
        'recovery_description' => 'Each code signs you in once if you lose your phone. Keep them in a password manager; they are shown only now.',
        'new_recovery_codes' => 'New recovery codes',
        'turn_off' => 'Turn off',
        'turn_on' => 'Turn on',
    ],
    'passkeys' => [
        'heading' => 'Passkeys',
        'description' => 'Sign in with your fingerprint, face or device PIN instead of a password.',
        'added_on' => 'Added :when',
        'last_used' => 'last used :when',
        'remove' => 'Remove',
        'confirm_remove' => 'Remove the passkey :name?',
        'empty' => 'No passkeys yet.',
        'unsupported' => 'This browser does not support passkeys.',
        'name' => 'Passkey name',
        'add' => 'Add passkey',
        'added' => 'Passkey added.',
    ],
];
