<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Runner\ChangeSet;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Write side of page paths (ADR-0011). Builds each page's path per language from every
 * ancestor's name in that language, falling back to the default language's name. A changed
 * path deactivates the old row instead of deleting it, so old URLs redirect.
 *
 * @internal
 */
final readonly class PathBuilder
{
    public function __construct(
        private LanguageRegistry $languages,
        private TemplateRegistry $templates,
        private PathResolver $resolver,
    ) {}

    /**
     * Rebuild paths for a page and, optionally, all its descendants.
     *
     * @return int number of path rows changed
     */
    public function rebuild(int $pageId, bool $descendants = true, ?ChangeSet $changes = null): int
    {
        $ids = $descendants
            ? DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $pageId)->orderBy('depth')->pluck('descendant_id')->map(fn ($id): int => Value::int($id))->all()
            : [$pageId];

        $changed = 0;

        foreach ($ids as $id) {
            $changed += $this->rebuildOne($id, $changes);
        }

        $this->resolver->flush();

        return $changed;
    }

    private function rebuildOne(int $pageId, ?ChangeSet $changes): int
    {
        $chain = DB::table(Tables::PAGE_CLOSURE)
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', Tables::PAGE_CLOSURE.'.ancestor_id')
            ->where('descendant_id', $pageId)
            ->orderByDesc('depth')
            ->get([Tables::PAGES.'.id', Tables::PAGES.'.parent_id', Tables::PAGES.'.template_id']);

        $routable = $chain->every(fn (object $p): bool => $this->templates->get(Value::int($p->template_id))->hasUrl());
        $names = DB::table(Tables::PAGE_LANGUAGES)->whereIn('page_id', $chain->pluck('id'))->get()
            ->groupBy('page_id')->map(fn ($rows) => $rows->keyBy('language_id'));
        $defaultId = $this->languages->default()->id;
        $changed = 0;

        foreach ($this->languages->all() as $language) {
            $own = $names[$pageId][$language->id] ?? null;
            $path = null;

            if ($routable && ($own !== null || $language->id === $defaultId)) {
                $segments = [];

                foreach ($chain as $ancestor) {
                    if ($ancestor->parent_id === null) {
                        continue; // the root page has no segment
                    }

                    $row = $names[$ancestor->id][$language->id] ?? $names[$ancestor->id][$defaultId] ?? null;
                    $segments[] = Value::string($row->name ?? $ancestor->id);
                }

                $path = implode('/', $segments);
            }

            $changed += $this->store($pageId, $language->id, $path, $changes, $language->code);
        }

        return $changed;
    }

    private function store(int $pageId, int $languageId, ?string $path, ?ChangeSet $changes, string $code): int
    {
        $active = DB::table(Tables::PAGE_PATHS)
            ->where('page_id', $pageId)->where('language_id', $languageId)->where('active', true)->first();

        if ($active !== null && $path !== null && $active->path === $path) {
            return 0;
        }

        if ($active !== null) {
            DB::table(Tables::PAGE_PATHS)->where('id', $active->id)->update(['active' => false]);
        }

        if ($path === null) {
            return $active === null ? 0 : 1;
        }

        $previous = DB::table(Tables::PAGE_PATHS)
            ->where('page_id', $pageId)->where('language_id', $languageId)->where('path', $path)->first();

        if ($previous !== null) {
            DB::table(Tables::PAGE_PATHS)->where('id', $previous->id)->update(['active' => true]);
        } else {
            DB::table(Tables::PAGE_PATHS)->insert([
                'page_id' => $pageId,
                'language_id' => $languageId,
                'path' => $path,
                'active' => true,
                'created_at' => now(),
            ]);
        }

        $changes?->updated('path', $pageId, "Path [{$code}] /{$path}/".($active !== null ? ' (was /'.Value::string($active->path).'/, now redirects)' : ''), [
            'language' => $code,
            'path' => $path,
            'previous' => $active?->path,
        ]);

        return 1;
    }
}
