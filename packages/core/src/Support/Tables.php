<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

/**
 * System table names. Everything Lynx owns is prefixed with "lynx_" so it never
 * collides with the host application's tables.
 *
 * @internal
 */
final class Tables
{
    public const string LANGUAGES = 'lynx_languages';

    public const string SETTINGS = 'lynx_settings';

    public const string FIELDS = 'lynx_fields';

    public const string TEMPLATES = 'lynx_templates';

    public const string TEMPLATE_FIELDS = 'lynx_template_fields';

    public const string PAGES = 'lynx_pages';

    public const string PAGE_CLOSURE = 'lynx_page_closure';

    public const string PAGE_LANGUAGES = 'lynx_page_languages';

    public const string PAGE_PATHS = 'lynx_page_paths';

    public const string FIELD_VALUE_META = 'lynx_value_meta';

    public const string ACTIVITY = 'lynx_activity_events';

    public const string ROLES = 'lynx_roles';

    public const string ROLE_PERMISSIONS = 'lynx_role_permissions';

    public const string ROLE_USER = 'lynx_role_user';

    /** Where each branch in the trash came from, for pages.restore. */
    public const string TRASH = 'lynx_trash';

    /** Scheduled publishing and unpublishing per page and language. */
    public const string SCHEDULES = 'lynx_schedules';

    public const string STRINGS = 'lynx_strings';

    /** Who is editing a page, so others cannot save over them. */
    public const string EDIT_LOCKS = 'lynx_edit_locks';

    /** A page's field values after each save that changed them. */
    public const string REVISIONS = 'lynx_revisions';

    /** The search index: one row of folded words per page and language (ADR-0054). */
    public const string SEARCH = 'lynx_search';

    /** SQLite only: the FTS5 table over SEARCH. */
    public const string SEARCH_FTS = 'lynx_search_fts';

    /** Sort keys: the value a page shows per field and language, for sorting (ADR-0061). */
    public const string SORT_KEYS = 'lynx_sort_keys';

    /** Field tables: lynx_f_{name}, e.g. lynx_f_title. */
    public const string FIELD_PREFIX = 'lynx_f_';

    /** PostgreSQL silently truncates identifiers longer than this. */
    public const int MAX_IDENTIFIER = 63;

    /** Longest index-name suffix added to a field table (FieldSchemaManager): "_lang", "_data". */
    public const int FIELD_INDEX_SUFFIX = 5;

    public static function field(string $name): string
    {
        return self::FIELD_PREFIX.$name;
    }

    /**
     * Longest field name whose table and index names fit in an identifier, given the
     * connection's table prefix: 51 characters without one.
     */
    public static function maxFieldNameLength(string $connectionPrefix = ''): int
    {
        return self::MAX_IDENTIFIER - strlen($connectionPrefix) - strlen(self::FIELD_PREFIX) - self::FIELD_INDEX_SUFFIX;
    }
}
