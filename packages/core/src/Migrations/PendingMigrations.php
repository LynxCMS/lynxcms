<?php

declare(strict_types=1);

namespace LynxCms\Core\Migrations;

use Illuminate\Database\Migrations\Migrator;
use LynxCms\Core\Support\Value;

/**
 * The migrations of Lynx packages (lynxcms/*) that have not run yet. Packages register their
 * migrations with loadMigrationsFrom(); a path belongs to the package whose composer.json sits
 * two folders above it (database/migrations).
 *
 * @internal
 */
final readonly class PendingMigrations
{
    private const string VENDOR = 'lynxcms/';

    public function __construct(private Migrator $migrator) {}

    /**
     * Package name => names of its pending migrations, in the order they run.
     *
     * @return array<string, list<string>>
     */
    public function byPackage(): array
    {
        $ran = $this->migrator->repositoryExists() ? $this->migrator->getRepository()->getRan() : [];
        $pending = [];

        foreach ($this->paths() as $package => $path) {
            $names = array_values(array_diff(array_keys($this->migrator->getMigrationFiles([$path])), $ran));

            if ($names !== []) {
                $pending[$package] = [...$pending[$package] ?? [], ...$names];
            }
        }

        return $pending;
    }

    /**
     * Package name => migrations folder, for Lynx packages only.
     *
     * @return array<string, string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->migrator->paths() as $path) {
            $package = $this->packageOf($path);

            if ($package !== null && str_starts_with($package, self::VENDOR)) {
                $paths[$package] = $path;
            }
        }

        return $paths;
    }

    private function packageOf(string $path): ?string
    {
        $manifest = dirname($path, 2).'/composer.json';
        $contents = is_file($manifest) ? file_get_contents($manifest) : false;

        return $contents === false ? null : Value::nullableString(Value::array(json_decode($contents, true))['name'] ?? null);
    }
}
