<?php

declare(strict_types=1);

return [

    /*
    | Front-end routing. When enabled, Lynx registers one catch-all route that resolves the
    | language from the URL prefix and the page from its path (ADR-0007, ADR-0011).
    */
    'routes' => [
        'enabled' => env('LYNX_ROUTES', true),
        'middleware' => ['web'],
    ],

    /*
    | Page URLs have no trailing slash ("/courses/risk"), as Laravel writes its URLs (ADR-0065).
    | Set true for "/courses/risk/", as ProcessWire writes them, e.g. to keep a migrated site's
    | addresses. Both forms open the page either way.
    */
    'trailing_slash' => env('LYNX_TRAILING_SLASH', false),

    /*
    | Blade views for templates live at "{views_path}.{template}", e.g. templates.course.
    */
    'views_path' => 'templates',

    /*
    | @string calls without a scope in views under these folders are site strings, shared by
    | every page (ADR-0051): a layout's footer note is one text, not one per page.
    */
    'strings' => [
        'site_folders' => ['layouts'],
    ],

    /*
    | Language detection. Without a URL prefix the order is: session, logged-in user, then the
    | default language. Accept-Language is only used on a first visit when enabled.
    */
    'localization' => [
        'remember' => true,
        'accept_language' => false,
        'redirect_default_prefix' => true,
    ],

    /*
    | MCP server and AI agent.
    | allowed_languages: languages an agent may write. Empty means all languages.
    | default_limit / max_limit: pages returned by find_pages when the agent sets no limit, and
    | the most it can ask for (in the limit argument or "limit=" in the selector).
    */
    /*
    | How long an editor's lock on a page lasts without being renewed, in seconds. The admin
    | renews it every minute while the page is open; a closed tab frees it after this.
    */
    /*
    | Content backups (lynx:backup): the schema and every page as a .zip. Not a database backup:
    | activity, revisions, users and app tables are not in it. schedule: null, "daily" or "hourly".
    */
    'backups' => [
        'path' => env('LYNX_BACKUPS_PATH'),
        'keep' => (int) env('LYNX_BACKUPS_KEEP', 14),
        'schedule' => env('LYNX_BACKUPS_SCHEDULE'),
    ],

    'revisions' => [
        // Revisions kept per page; older ones are deleted as new saves come in.
        'keep' => (int) env('LYNX_REVISIONS_KEEP', 50),
    ],

    'edit_lock_seconds' => (int) env('LYNX_EDIT_LOCK_SECONDS', 120),

    'permissions' => [
        // Role given to MCP clients and agents that act for no user. Null refuses them once
        // roles are in use (ADR-0033).
        'anonymous_role' => env('LYNX_ANONYMOUS_ROLE'),

        // Role given to people who sign up on the site (Laravel's Registered event). "guest"
        // grants nothing: the website, never the admin. Null gives none (ADR-0073).
        'registration_role' => env('LYNX_REGISTRATION_ROLE', 'guest'),
    ],

    // Translatable fields sorted through stored sort keys (ADR-0061): sort=title reads an index
    // instead of sorting every page by its value with fallback. Run lynx:sort-keys after changing.
    'sort_keys' => ['title'],

    // PostgreSQL trigram indexes for %=, *=, ~=, ^= and $= on text fields (ADR-0061). Needs the
    // pg_trgm extension; lynx:trigram installs it and indexes the existing fields.
    'trigram' => (bool) env('LYNX_TRIGRAM', false),

    'agents' => [
        'allowed_languages' => [],
        'default_limit' => env('LYNX_AGENT_DEFAULT_LIMIT', 25),
        'max_limit' => env('LYNX_AGENT_MAX_LIMIT', 100),
    ],

    'mcp' => [
        'web' => [
            'enabled' => env('LYNX_MCP_WEB', false),
            'path' => '/mcp/lynx',
            'middleware' => ['auth:sanctum'],
        ],
    ],

    'ai' => [
        'provider' => env('LYNX_AI_PROVIDER'),
        'model' => env('LYNX_AI_MODEL'),
        'translation_model' => env('LYNX_AI_TRANSLATION_MODEL'),
        'max_steps' => 25,
    ],
];
