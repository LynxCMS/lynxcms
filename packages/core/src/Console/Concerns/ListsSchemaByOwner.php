<?php

declare(strict_types=1);

namespace LynxCms\Core\Console\Concerns;

/**
 * Lists the site's own templates or fields first, and the ones Lynx and its modules own (menus,
 * trash, SEO settings, ADR-0115) in a table of their own with the owner, so the schema a site
 * defines isn't lost among what it merely installs.
 *
 * @internal
 */
trait ListsSchemaByOwner
{
    use PrintsFittedTables;

    /**
     * @param  list<string>  $headers
     * @param  list<array{cells: list<string>, owner: string|null}>  $rows
     */
    protected function tablesByOwner(array $headers, array $rows): void
    {
        $site = [];
        $system = [];

        foreach ($rows as ['cells' => $cells, 'owner' => $owner]) {
            if ($owner === null) {
                $site[] = $cells;
            } else {
                $system[] = [...$cells, $owner];
            }
        }

        if ($system === []) {
            $this->fittedTable($headers, $site);

            return;
        }

        if ($site !== []) {
            $this->line('<options=bold>Site</>');
            $this->fittedTable($headers, $site);
            $this->newLine();
        }

        $this->line('<options=bold>System</> (Lynx and modules; read only in the admin)');
        $this->fittedTable([...$headers, 'Owner'], $system);
    }
}
