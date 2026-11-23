<?php

declare(strict_types=1);

namespace LynxCms\Core\Api\Concerns;

use LynxCms\Core\Actions\Pages\DeleteRedirect;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\OldPath;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/**
 * $lynx->pages methods about a page's history and links: its old URLs (and deleting one), the pages
 * that reference it, and its activity. Part of PagesApi, kept apart to keep that class small.
 *
 * @internal
 */
trait ManagesPageHistory
{
    /**
     * Paths the page had before renames and moves, newest first. Each redirects to the page's
     * current URL unless another page has taken it since.
     *
     * @return list<OldPath>
     */
    public function redirects(Page|int|string $page): array
    {
        $id = $this->get($page)->id ?? null;
        $redirects = [];

        foreach ($id === null ? [] : $this->paths->history($id) as [$languageId, $path]) {
            $language = $this->languages->find($languageId);

            if ($language !== null) {
                $redirects[] = new OldPath($language, $path, ($this->paths->resolve($language, $path)[0] ?? null) === $id);
            }
        }

        return $redirects;
    }

    /**
     * Delete an old path so it stops redirecting. $path has no language prefix ("courses/delivery-8");
     * $language defaults to the default language.
     */
    public function deleteRedirect(Page|int|string $page, string $path, ?string $language = null, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(DeleteRedirect::class, array_filter([
            'page' => $this->ref($page),
            'path' => $path,
            'language' => $language,
        ], fn (string|int|null $value): bool => $value !== null), $dryRun)->valueOrDryRun(Page::class);
    }

    /**
     * Pages that reference these pages in a field that references pages, whatever their status,
     * lowest id first. A selector narrows them, e.g. "template!=menu-item". With $branch, each
     * page counts with every page below it, and references from inside those branches or from
     * the trash are left out: what a trash confirmation lists. total() counts past the limit.
     *
     * @param  Page|int|string|list<Page|int|string>  $pages
     */
    public function backlinks(Page|int|string|array $pages, int $limit = 50, string $selector = '', bool $branch = false): PageArray
    {
        $targets = $this->pageIds(is_array($pages) ? $pages : [$pages]);
        $ids = $branch ? $this->branchBacklinks->outsideBranches($targets) : $this->backlinks->referencing($targets);
        $selector = trim($selector) === '' ? '' : trim($selector).', ';

        return $ids === [] ? new PageArray([]) : $this->finder->find($selector.'id='.implode('|', $ids).', include=all, sort=id, limit='.max(1, $limit));
    }

    /**
     * @param  list<Page|int|string>  $pages
     * @return list<int> the ids of those that exist
     */
    private function pageIds(array $pages): array
    {
        return array_values(array_filter(array_map(fn (Page|int|string $page): int => (int) ($this->get($page)->id ?? 0), $pages)));
    }

    /**
     * Activity log entries about this page, newest first.
     *
     * @return list<ActivityEvent>
     */
    public function activity(Page|int|string $page, int $limit = 20): array
    {
        return array_values(ActivityEvent::query()
            ->where('subject_type', 'page')
            ->where('subject_id', (string) ($this->get($page)->id ?? 0))
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->all());
    }
}
