<?php

declare(strict_types=1);

namespace LynxCms\Core\Install;

use Closure;
use Composer\InstalledVersions;

/**
 * The free modules lynx:install can add with Composer, by short name, and which of them a site
 * lacks. Paid modules live in private repositories and are installed by hand.
 *
 * @internal
 */
final readonly class ModuleCatalog
{
    /** Short name => what it adds, as the picker lists it. */
    public const array MODULES = [
        'admin' => 'Admin: the screens editors use to manage pages, fields, and templates',
        'mcp' => 'MCP: a server that lets AI clients read and change the site through its actions',
        'ai' => 'AI: the built-in agent and machine translation',
        'menus' => 'Menus: menus built from pages and links, edited in the admin',
        'media' => 'Media: image and file fields',
        'forms' => 'Forms: forms visitors send, with submissions in the admin',
        'seo' => 'SEO: titles, descriptions, and social cards for each page',
        'sitemap' => 'Sitemap: sitemap.xml for search engines',
        'cache' => 'Page cache: whole pages served from a cache',
        'page-password' => 'Page passwords: pages and branches behind a password',
        'finder' => 'Page Finder: search every page by its fields in the admin',
        'static-site' => 'Static site: export the site as HTML files',
    ];

    public const string DEFAULT = 'admin';

    /** @var Closure(string): bool */
    private Closure $isInstalled;

    /**
     * @param  (Closure(string): bool)|null  $isInstalled  whether a package is installed (Composer's record by default)
     * @param  string|null  $version  the version of lynxcms/core, which every module shares; null reads it from Composer
     */
    public function __construct(?Closure $isInstalled = null, private ?string $version = null)
    {
        $this->isInstalled = $isInstalled ?? InstalledVersions::isInstalled(...);
    }

    public static function package(string $name): string
    {
        return 'lynxcms/'.$name;
    }

    /**
     * The modules the site lacks, by short name.
     *
     * @return array<string, string>
     */
    public function missing(): array
    {
        return array_filter(self::MODULES, fn (string $name): bool => ! ($this->isInstalled)(self::package($name)), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Short names from a comma separated list, checked: unknown names come back separately.
     *
     * @return array{0: list<string>, 1: list<string>} known, unknown
     */
    public function parse(string $names): array
    {
        $given = array_values(array_unique(array_filter(array_map(trim(...), explode(',', strtolower($names))))));

        return [
            array_values(array_filter($given, fn (string $name): bool => isset(self::MODULES[$name]))),
            array_values(array_filter($given, fn (string $name): bool => ! isset(self::MODULES[$name]))),
        ];
    }

    /**
     * Composer requirements for the modules the site lacks, at the version of lynxcms/core.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function requirements(array $names): array
    {
        $missing = $this->missing();
        $version = $this->version ?? InstalledVersions::getPrettyVersion('lynxcms/core');
        $constraint = $version === null ? '' : ':'.ltrim($version, 'v');

        return array_values(array_map(fn (string $name): string => self::package($name).$constraint, array_filter($names, fn (string $name): bool => isset($missing[$name]))));
    }
}
