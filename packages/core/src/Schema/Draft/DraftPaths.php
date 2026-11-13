<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

/**
 * The files a draft build keeps up to date (ADR-0116), each only when the site has it: the schema
 * folder, the types Lynx's PHPStan extension reads, and the IDE helper.
 *
 * @api
 */
final readonly class DraftPaths
{
    public function __construct(
        public string $schema,
        public string $phpstanTypes,
        public string $ideHelper,
    ) {}

    /** Where lynx:schema:export, lynx:phpstan and lynx:ide-helper write by default. */
    public static function defaults(): self
    {
        return new self(base_path('lynx/schema'), base_path('lynx/phpstan/types.php'), base_path('_ide_helper_lynx.php'));
    }
}
