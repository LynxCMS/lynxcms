<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Fragments\FragmentClearer;
use LynxCms\Core\Events\ContentChanged;

/**
 * Clears stored pages and fragments after content changed (ADR-0064): on a ContentChanged event, and after
 * actions that change what pages show without one (fields, templates, page strings).
 *
 * @internal
 */
final readonly class ClearOnChange
{
    public function __construct(private ChangeScopes $scopes, private PageCacheClearer $clearer, private FragmentClearer $fragments) {}

    public function handle(ContentChanged $event): void
    {
        $changed = $event->affectedPages();
        $ids = $changed === null ? null : $this->scopes->pagesToClear($changed);

        $ids === null ? $this->clearer->clearAll() : $this->clearer->clearPages($ids);
        $changed === null ? $this->fragments->clearAll() : $this->fragments->clearDependingOn($this->dependencies($changed));
    }

    /** For action hooks, which run inside the action's transaction: a rollback clears nothing. */
    public function clearAllAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            $this->clearer->clearAll();
            $this->fragments->clearAll();
        });
    }

    /**
     * page:{id} for each changed page, and template:{name} when a fragment depends on a template,
     * which is the only case worth loading the pages for.
     *
     * @param  list<int>  $pageIds
     * @return list<string>
     */
    private function dependencies(array $pageIds): array
    {
        $dependencies = array_map(fn (int $id): string => 'page:'.$id, $pageIds);

        if (! FragmentEntry::query()->where('depends_on', 'like', '%,template:%')->exists()) {
            return $dependencies;
        }

        foreach ($pageIds as $id) {
            $template = lynx()->pages->get($id)?->template()->name;
            $dependencies[] = $template === null ? 'page:'.$id : 'template:'.$template;
        }

        return array_values(array_unique($dependencies));
    }
}
