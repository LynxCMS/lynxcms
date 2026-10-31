<?php

declare(strict_types=1);

return [
    'title' => 'General',
    'description' => 'How the admin introduces itself to everyone who signs in.',
    'not_found' => [
        'heading' => 'Page not found',
        'help' => 'The page shown, with status 404, when an address matches no page. Editors write it like any other page. With none, the site shows its plain 404.',
        'search' => 'Search for a page',
        'not_shown' => 'That page is unpublished or in the trash, so the plain 404 shows instead.',
        'save' => 'Save',
        'edit' => 'Edit the page',
        'denied' => 'Only superusers choose the page not found.',
        'saved' => 'Saved.',
        'saved_plain' => 'Saved. Missing pages show the plain 404.',
    ],
    'branding' => [
        'heading' => 'Branding',
        'intro' => 'The site\'s name, logo and colours on the sign-in screens and at the top of the sidebar. Leave a field empty for the default.',
        'name' => 'Name',
        'name_help' => 'Shown next to the initial when there is no logo, in browser tabs, and read out instead of the logo.',
        'logo' => 'Logo',
        'logo_help' => 'PNG, JPEG, WebP or SVG, up to 1 MB. A wide logo with the name in it works best; it replaces the name.',
        'remove_logo' => 'Remove the uploaded logo',
        'remove_logo_configured' => 'Remove the uploaded logo (back to the configured one)',
        'background' => 'Sign-in background',
        'background_help' => 'Behind the sign-in card. As #rrggbb.',
        'accent' => 'Accent',
        'accent_help' => 'Buttons, links and focus rings. As #rrggbb.',
        'pick' => ':color: pick',
        'default_placeholder' => 'default',
        'default' => 'Default',
        'save' => 'Save',
        'preview_label' => 'Preview of the sign-in screen',
        'preview' => 'Preview',
        'svg_note' => 'The new SVG logo shows once it is saved.',
        'denied' => 'Only superusers change the branding.',
        'saved' => 'Saved. The sign-in screens and the sidebar show it from the next page.',
    ],
];
