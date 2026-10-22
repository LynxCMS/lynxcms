<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Roles\DeleteRole;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleGrant;
use LynxCms\Core\Support\Value;
use Symfony\Component\Yaml\Yaml;

/**
 * Roles in schema sync (ADR-0014, ADR-0033): one YAML file per role with its grants, branches
 * written as default-language paths because page ids differ between environments. Who holds a
 * role is never exported: assignments stay per environment.
 *
 * @internal
 */
final readonly class RoleSync
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array<string, array{name: string, label: string, superuser: bool, grants: list<array<string, mixed>>}> name => file data
     */
    public function export(): array
    {
        $items = [];

        foreach (Role::query()->with('grants')->orderBy('name')->get() as $role) {
            $items[$role->name] = [
                'name' => $role->name,
                'label' => $role->label,
                'superuser' => $role->is_superuser,
                'grants' => array_values($role->grants->map($this->grantData(...))->all()),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $directory): array
    {
        return array_map(
            fn (string $file): array => Value::map(Yaml::parseFile($file)),
            Value::strings($this->files->glob(rtrim($directory, '/').'/roles/*.yaml')),
        );
    }

    /**
     * roles.save runs for roles whose file differs from the database. A grant under a page that
     * does not exist here is reported as an error and its role is skipped.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{0: class-string<Action>, 1: array<string, mixed>}>
     */
    public function saves(array $items, SchemaSyncResult $result): array
    {
        $current = $this->export();
        $runs = [];

        foreach ($items as $item) {
            $name = Value::string($item['name'] ?? '');
            $grants = ($current[$name] ?? null) == $item ? null : $this->grantsInput(Value::array($item['grants'] ?? []), $name, $result);

            if ($grants !== null) {
                $runs[] = [SaveRole::class, ['name' => $name, 'label' => Value::string($item['label'] ?? $name), 'superuser' => (bool) ($item['superuser'] ?? false), 'grants' => $grants]];
            }
        }

        return $runs;
    }

    /**
     * roles.delete runs for roles missing from the files, with $force; otherwise they are skipped.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{0: class-string<Action>, 1: array<string, mixed>}>
     */
    public function deletes(array $items, bool $force, SchemaSyncResult $result): array
    {
        $inFiles = array_map(fn (array $item): string => Value::string($item['name'] ?? ''), $items);
        $runs = [];

        foreach (array_diff(array_keys($this->export()), $inFiles) as $name) {
            if ($force) {
                $runs[] = [DeleteRole::class, ['role' => $name]];
            } else {
                $result->skipped[] = "Delete role {$name} (not in the files; run with --force to delete)";
            }
        }

        return $runs;
    }

    /**
     * @return array<string, mixed>
     */
    private function grantData(RoleGrant $grant): array
    {
        $paths = array_map(function (int $id): string {
            $page = lynx()->pages->get($id);

            return $page instanceof Page ? '/'.trim((string) $page->path(lynx()->languages->default()), '/').($page->parentId === null ? '' : '/') : "#{$id}";
        }, $grant->branches ?? []);

        return array_filter(['permission' => $grant->permission, 'templates' => $grant->templates, 'branches' => $paths], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Grants for roles.save, branch paths resolved to this environment's page ids.
     *
     * @param  array<array-key, mixed>  $grants
     * @return list<array<string, mixed>>|null null when a branch page is missing (reported as an error)
     */
    private function grantsInput(array $grants, string $role, SchemaSyncResult $result): ?array
    {
        $input = [];

        foreach ($grants as $grant) {
            $grant = Value::map($grant);
            $branches = [];

            foreach (Value::strings($grant['branches'] ?? []) as $path) {
                $page = lynx()->pages->get($path);

                if (! $page instanceof Page) {
                    $result->errors[] = "roles.save: role [{$role}] grants [".Value::string($grant['permission'] ?? '')."] under [{$path}], which does not exist here.";

                    return null;
                }

                $branches[] = (int) $page->id;
            }

            $input[] = array_filter(['permission' => $grant['permission'] ?? null, 'templates' => $grant['templates'] ?? null, 'branches' => $branches], fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return $input;
    }
}
