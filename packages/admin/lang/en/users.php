<?php

declare(strict_types=1);

return [
    'title' => 'Users',
    'description' => 'People who can sign in, and the roles they hold.',
    'saved' => 'Saved.',
    'list' => [
        'search' => 'Search',
        'search_placeholder' => 'Name or email',
        'user' => 'User',
        'roles' => 'Roles',
        'save' => 'Save',
        'sent' => 'Sent :when',
        'pending' => 'Invitation pending',
        'resend' => 'Resend invitation',
        'empty' => 'No users match.',
    ],
    'invite' => [
        'heading' => 'Invite user',
        'help' => 'They get an email with a link to choose their password. There is no sign-up form.',
        'name' => 'Name',
        'email' => 'Email',
        'roles' => 'Roles',
        'send' => 'Send invitation',
        'sent' => 'Invitation sent to :email.',
        'sent_again' => 'Invitation sent again to :email.',
        'email_taken' => 'Someone with this email can already sign in.',
        'not_pending' => 'This user has no pending invitation.',
    ],
    'mail' => [
        'subject' => 'You are invited to :site',
        'greeting' => 'Hello :name,',
        'intro' => 'You have been invited to manage the content of :site. Choose a password to sign in.',
        'action' => 'Choose your password',
        'outro' => 'The link works for :days days. If you were not expecting this invitation, ignore this email.',
    ],
];
