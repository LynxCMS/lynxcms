<?php

declare(strict_types=1);

namespace LynxCms\Seo;

use LynxCms\Core\Fields\FieldTypeRegistry;

/**
 * Names of the fields and the template the SEO module installs (ADR-0055). Site-wide settings
 * live on a hidden system page (ADR-0008); the page fields are added to the templates that ask
 * for them (seo.enable_template).
 *
 * @api
 */
final class SeoSchema
{
    public const string SETTINGS_TEMPLATE = 'seo-settings';

    public const string SETTINGS_NAME = 'seo-settings';

    public const string TITLE = 'seo_title';

    public const string DESCRIPTION = 'seo_description';

    public const string IMAGE = 'seo_image';

    public const string NOINDEX = 'seo_noindex';

    public const string CANONICAL = 'seo_canonical';

    public const string SITE_NAME = 'seo_site_name';

    public const string TITLE_PATTERN = 'seo_title_pattern';

    public const string DEFAULT_DESCRIPTION = 'seo_default_description';

    public const string DEFAULT_IMAGE = 'seo_default_image';

    public const string TWITTER = 'seo_twitter';

    public const string ROBOTS_EXTRA = 'seo_robots_extra';

    /** Who runs the site, for structured data: an organization (default) or a person. */
    public const string ORG_TYPE = 'seo_org_type';

    public const string ORG_PERSON = 'person';

    public const string ORG_NAME = 'seo_org_name';

    public const string ORG_LOGO = 'seo_org_logo';

    /** The site's profiles elsewhere, one URL per line. */
    public const string ORG_SAME_AS = 'seo_org_same_as';

    /** Template mappings to schema.org types, one matrix item each (ADR-0056). */
    public const string SCHEMAS = 'seo_schemas';

    public const string SCHEMA_TEMPLATE = 'seo_schema_template';

    public const string SCHEMA_TYPE = 'seo_schema_type';

    public const string SCHEMA_MAP = 'seo_schema_map';

    public const string SCHEMA_ITEM = 'mapping';

    /** The page editor tab that holds a template's SEO fields. */
    public const string TAB = 'seo';

    /** Help shown under each field in the page editor. */
    private const array HELP = [
        self::TITLE => 'Shown as the title in search results and shared links. Empty: the page title in the site\'s title pattern.',
        self::DESCRIPTION => 'Shown under the title in search results, about 155 characters. Empty: taken from the page\'s text.',
        self::IMAGE => 'Shown when the page is shared, cropped to 1200 by 630 pixels.',
        self::NOINDEX => 'Ask search engines to leave this page out of their results.',
        self::CANONICAL => 'Only when this page repeats another: the address of the original.',
        self::SITE_NAME => 'Added to page titles and shown on shared links.',
        self::TITLE_PATTERN => 'How page titles read: {title} is the page title, {site} the site name. Empty: {title} | {site}.',
        self::DEFAULT_DESCRIPTION => 'For pages with no description and no text to take one from.',
        self::DEFAULT_IMAGE => 'For shared pages without an image of their own.',
        self::TWITTER => 'The site\'s account on X, e.g. @lynxcms.',
        self::ROBOTS_EXTRA => 'Added to robots.txt, one rule per line, e.g. Disallow: /private/. Outside production robots.txt blocks everything.',
        self::ORG_TYPE => 'Who runs the site, for search engines.',
        self::ORG_NAME => 'Empty: the site name.',
        self::ORG_SAME_AS => 'The site\'s profiles on other sites, one address per line.',
        self::SCHEMAS => 'Describe a template\'s pages to search engines as a schema.org type, e.g. Event. Edit these in SEO, Structured data.',
    ];

    /** Image fields need lynxcms/media; without it there are no share images. */
    private const string IMAGES_TYPE = 'images';

    /**
     * Fields to create: name => [type, translatable, label, settings]. Image fields only when
     * lynxcms/media is installed.
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: array<string, mixed>}>
     */
    public static function fields(): array
    {
        return array_filter(self::definitions(), fn (array $field): bool => $field[0] !== self::IMAGES_TYPE || app(FieldTypeRegistry::class)->has(self::IMAGES_TYPE));
    }

    /**
     * Every field name the module may install, whichever packages are present.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Template field specs for $names, each with its help text for editors.
     *
     * @param  list<string>  $names
     * @return list<array{name: string, description: string}>
     */
    public static function specs(array $names): array
    {
        return array_map(fn (string $name): array => ['name' => $name, 'description' => self::HELP[$name] ?? ''], $names);
    }

    /**
     * Template field specs for page fields: in the SEO tab, "Hide from search engines" in its sidebar.
     *
     * @param  list<string>  $names
     * @return list<array{name: string, description: string, tab: string, column?: string}>
     */
    public static function pageSpecs(array $names): array
    {
        return array_map(fn (array $spec): array => self::placement($spec['name']) + $spec, self::specs($names));
    }

    /**
     * Where a page field goes: the SEO tab, and its sidebar for "Hide from search engines".
     *
     * @return array{tab: string, column?: string}
     */
    public static function placement(string $name): array
    {
        return ['tab' => self::TAB] + ($name === self::NOINDEX ? ['column' => 'sidebar'] : []);
    }

    /**
     * A template's tabs with the SEO tab last, added when missing.
     *
     * @param  list<mixed>  $tabs
     * @return list<mixed>
     */
    public static function withTab(array $tabs): array
    {
        return self::hasTab($tabs) ? $tabs : [...$tabs, ['key' => self::TAB, 'label' => 'SEO']];
    }

    /**
     * @param  list<mixed>  $tabs
     * @return list<mixed>
     */
    public static function withoutTab(array $tabs): array
    {
        return array_values(array_filter($tabs, fn (mixed $tab): bool => ! is_array($tab) || ($tab['key'] ?? null) !== self::TAB));
    }

    /**
     * @param  list<mixed>  $tabs
     */
    public static function hasTab(array $tabs): bool
    {
        return count(self::withoutTab($tabs)) < count($tabs);
    }

    /**
     * The fields a template gets with SEO turned on.
     *
     * @return list<string>
     */
    public static function pageFields(): array
    {
        return array_values(array_intersect([self::TITLE, self::DESCRIPTION, self::IMAGE, self::NOINDEX, self::CANONICAL], array_keys(self::fields())));
    }

    /**
     * The settings page's fields.
     *
     * @return list<string>
     */
    public static function settingsFields(): array
    {
        return array_values(array_diff(array_keys(self::fields()), self::pageFields(), [self::SCHEMA_TEMPLATE, self::SCHEMA_TYPE, self::SCHEMA_MAP]));
    }

    /** @return array<string, array{0: string, 1: bool, 2: string, 3: array<string, mixed>}> */
    private static function definitions(): array
    {
        return [
            self::TITLE => ['text', true, 'SEO title', []],
            self::DESCRIPTION => ['textarea', true, 'SEO description', []],
            self::IMAGE => [self::IMAGES_TYPE, false, 'Share image', ['max_files' => 1]],
            self::NOINDEX => ['toggle', false, 'Hide from search engines', []],
            self::CANONICAL => ['url', false, 'Canonical URL', ['relative' => true]],
            self::SITE_NAME => ['text', true, 'Site name', []],
            self::TITLE_PATTERN => ['text', true, 'Title pattern', []],
            self::DEFAULT_DESCRIPTION => ['textarea', true, 'Default description', []],
            self::DEFAULT_IMAGE => [self::IMAGES_TYPE, false, 'Default share image', ['max_files' => 1]],
            self::TWITTER => ['text', false, 'X (Twitter) account', []],
            self::ROBOTS_EXTRA => ['textarea', false, 'Extra robots.txt lines', []],
            self::ORG_TYPE => ['choice', false, 'Site owner', ['options' => [['value' => 'organization', 'label' => 'Organization'], ['value' => self::ORG_PERSON, 'label' => 'Person']]]],
            self::ORG_NAME => ['text', false, 'Owner name', []],
            self::ORG_LOGO => [self::IMAGES_TYPE, false, 'Logo', ['max_files' => 1]],
            self::ORG_SAME_AS => ['textarea', false, 'Profiles elsewhere', []],
            self::SCHEMA_TEMPLATE => ['text', false, 'Template', []],
            self::SCHEMA_TYPE => ['text', false, 'Schema.org type', []],
            self::SCHEMA_MAP => ['textarea', false, 'Properties', []],
            self::SCHEMAS => ['matrix', false, 'Structured data', ['types' => [['slug' => self::SCHEMA_ITEM, 'label' => 'Template', 'fields' => [['name' => self::SCHEMA_TEMPLATE], ['name' => self::SCHEMA_TYPE], ['name' => self::SCHEMA_MAP]]]]]],
        ];
    }
}
