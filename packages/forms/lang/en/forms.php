<?php

declare(strict_types=1);

return [
    'yes' => 'Yes',
    'no' => 'No',
    'choose' => 'Choose…',
    'next' => 'Next',
    'back' => 'Back',
    'send' => 'Send',
    'sending' => 'Sending…',
    'step' => 'Step :current of :total',
    'check_fields' => 'Please check the fields marked below.',
    'too_many' => 'Too many attempts. Please try again in :seconds seconds.',
    'turnstile' => 'We couldn\'t confirm you\'re a person. Please try again.',
    'add_row' => 'Add',
    'remove' => 'Remove',
    'files_hint' => 'Up to :count files, :size each. :types',
    'uploading' => 'Uploading…',
    'summary' => 'Check your answers before sending.',
    'sign_in_to_answer' => 'Sign in to answer this form.',
    'sign_in' => 'Sign in',
    'already_answered' => 'You have already answered this form.',
    'sent' => 'Thank you. We received your message.',
    'failed' => 'Your message could not be sent. Please try again.',
    'mail' => [
        'subject' => 'New submission: :form',
        'open' => 'Open in the admin',
    ],
    'digest' => [
        'subject' => '{1} 1 new submission: :form|[2,*] :count new submissions: :form',
        'intro' => '{1} :form received 1 new submission since the last summary.|[2,*] :form received :count new submissions since the last summary.',
        'more' => 'And :count more.',
        'open' => 'Open them in the admin',
    ],
];
