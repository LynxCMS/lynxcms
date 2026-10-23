<?php

declare(strict_types=1);

/*
 * Atlas Training's own settings, from .env. Nothing here is a secret in the repository: tokens
 * stay in .env.
 */

return [
    // Who receives course enquiries
    'front_desk' => env('ATLAS_FRONT_DESK', 'hello@atlas-training.example'),

    // The CRM that receives leads (chapter 6)
    'crm' => [
        'url' => env('ATLAS_CRM_URL'),
        'token' => env('ATLAS_CRM_TOKEN'),
    ],

    // Stripe, for paid bookings (chapter 7): the secret key and the webhook's signing secret
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // The mailing service that keeps the newsletter list (chapter 6)
    'newsletter' => [
        'url' => env('ATLAS_NEWSLETTER_URL'),
        'token' => env('ATLAS_NEWSLETTER_TOKEN'),
    ],
];
