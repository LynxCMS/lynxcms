<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use InvalidArgumentException;

/**
 * Stylesheets modules add to every admin page, after the admin's own and before the site's
 * (lynx-admin.stylesheets), so a site's theme still wins (ADR-0106). A module builds its sheet from
 * resources/css/module.css and registers it in a service provider:
 * app(AdminAssets::class)->stylesheet('translate', __DIR__.'/../dist/admin.css').
 *
 * @api
 */
final class AdminAssets
{
    /** A stylesheet's name: lowercase letters, digits and dashes. */
    public const string NAME = '/^[a-z0-9][a-z0-9-]{0,63}$/';

    /** @var array<string, string> path by name */
    private array $stylesheets = [];

    /** @param  string  $path  the built CSS file */
    public function stylesheet(string $name, string $path): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException("[{$name}] is not a stylesheet name: use lowercase letters, digits and dashes.");
        }

        $this->stylesheets[$name] = $path;
    }

    /** @return array<string, string> path by name, in the order registered */
    public function stylesheets(): array
    {
        return $this->stylesheets;
    }

    public function path(string $name): ?string
    {
        return $this->stylesheets[$name] ?? null;
    }
}
