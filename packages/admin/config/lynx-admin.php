<?php

declare(strict_types=1);

return [

    /*
    | URL prefix of the admin, e.g. "lynx" serves the page tree at /lynx/pages.
    */
    'prefix' => env('LYNX_ADMIN_PREFIX', 'lynx'),

    /*
    | Middleware for every admin route. Keep "auth" (or your own guard) in production.
    */
    'middleware' => ['web', 'auth'],

    /*
    | Lynx's sign-in screens (Laravel Fortify) at /{prefix}/login, with password reset and no
    | registration: superusers invite users from Setup > Users. "auto" turns them on unless the app configures Fortify
    | itself (config/fortify.php); then the app's own screens sign users in.
    */
    'auth' => [
        'enabled' => env('LYNX_ADMIN_AUTH', 'auto'),
        // How long an invitation link works
        'invitation_days' => 7,
    ],

    /*
    | Colour scheme: "light", "dark" or "system" (follows the operating system).
    */
    'theme' => env('LYNX_ADMIN_THEME', 'system'),

    /*
    | BlatUI's customizer options (https://blatui.remix-it.com/themes), applied without a rebuild.
    | Values BlatUI does not know are ignored. Colour scheme is "theme" above.
    */
    'appearance' => [
        // Grey family: neutral, stone, zinc, slate, gray, mauve, olive, mist, taupe
        'base' => env('LYNX_ADMIN_BASE', 'stone'),
        // Accent colour: default, amber, blue, cyan, emerald, fuchsia, green, indigo, lime, orange,
        // pink, purple, red, rose, sky, teal, violet, yellow
        'theme' => env('LYNX_ADMIN_ACCENT', 'cyan'),
        // Corner radius in rem: 0, 0.3, 0.5, 0.625, 0.75, 1
        'radius' => env('LYNX_ADMIN_RADIUS', '0.3'),
        // Body font: sans (Instrument Sans), system, serif, mono, inter, geist, manrope, jakarta,
        // space-grotesk, dm-sans, outfit, sora, lora, source-serif
        'font' => env('LYNX_ADMIN_FONT', 'inter'),
        // Heading font: the same choices; sans follows the body font
        'heading_font' => env('LYNX_ADMIN_HEADING_FONT', 'mono'),
        // Shadows: none, sm, default, lg, xl
        'shadow' => env('LYNX_ADMIN_SHADOW', 'none'),
        // Density: compact, default, comfortable
        'spacing' => env('LYNX_ADMIN_SPACING', 'default'),
        // Letter spacing: tight, normal, wide
        'tracking' => env('LYNX_ADMIN_TRACKING', 'wide'),
        // Inputs: outline, fill, inset
        'input_style' => env('LYNX_ADMIN_INPUT_STYLE', 'outline'),
        // Load the chosen web fonts from fonts.bunny.net; turn off to self-host them (or for no
        // outside requests), and system fonts are used until yours load
        'load_fonts' => (bool) env('LYNX_ADMIN_LOAD_FONTS', true),
    ],

    /*
    | Extra stylesheet URLs loaded after the admin CSS. Redefine BlatUI's tokens (--primary,
    | --background…) or the --lynx-* tree tokens in them to re-theme the admin without rebuilding
    | it (php artisan vendor:publish --tag=lynx-admin-theme), or paste a theme from BlatUI's
    | customizer.
    */
    'stylesheets' => [],

    /*
    | Whether the Templates and Fields screens may change the schema. Turn it off where the schema
    | comes from YAML files in Git (php artisan lynx:schema:apply); the screens become read only.
    */
    'schema_editing' => (bool) env('LYNX_ADMIN_SCHEMA_EDITING', true),

    /*
    | The Attributes tab of the rich text link dialog: where a link may open and the relations
    | editors may give it. On save, relations other than nofollow, sponsored, ugc and noreferrer
    | are dropped, and links opening a new window get noopener. Add 'ugc' for sites whose
    | visitors write content. Empty lists hide the choice.
    */
    'links' => [
        'targets' => ['_blank' => 'New window'],
        'rels' => [
            'nofollow' => 'nofollow (search engines do not follow it)',
            'sponsored' => 'sponsored (paid or advertising)',
            'noreferrer' => 'noreferrer (hide where the visitor came from)',
        ],
    ],

    'tree' => [
        // Children shown per page before the numbered pager appears.
        'per_page' => 20,
    ],

];
