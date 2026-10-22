<?php

declare(strict_types=1);

namespace LynxCms\Core\Api\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LynxCms\Core\Actions\Pages\DiscardDraft;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

/**
 * Scheduled publishing, edit locks, revisions and drafts in the pages API (ADR-0036 to ADR-0039).
 *
 * @api
 */
trait VersionsPages
{
    /**
     * Schedule publishing in one language (ADR-0036). Null leaves an entry as it is, unless
     * $clear, which removes the entries not given.
     *
     * @return array<array-key, mixed>|ActionResult the entries changed: action => ISO time or null
     */
    public function schedule(Page|int|string $page, ?string $language = null, ?CarbonInterface $publishAt = null, ?CarbonInterface $unpublishAt = null, bool $clear = false, bool $dryRun = false): ActionResult|array
    {
        $input = ['page' => $this->ref($page)] + ($language === null ? [] : ['language' => $language]);

        foreach (['publish_at' => $publishAt, 'unpublish_at' => $unpublishAt] as $key => $at) {
            if ($at !== null || $clear) {
                $input[$key] = $at?->toIso8601String();
            }
        }

        $result = $this->runner->run(SchedulePage::class, $input, $dryRun);

        return $result->dryRun ? $result : $result->arrayValue();
    }

    /**
     * A page's scheduled publishing, by language then action.
     *
     * @return list<array{language: string, action: string, at: CarbonImmutable, error: string|null}>
     */
    public function schedules(Page|int $page): array
    {
        return $this->scheduleReader->of($page instanceof Page ? (int) $page->id : $page);
    }

    /**
     * Who is editing the page and until when, or null (ADR-0037).
     *
     * @return array{holder: string, acquired_at: CarbonImmutable, expires_at: CarbonImmutable}|null
     */
    public function editLock(Page|int $page): ?array
    {
        return $this->editLocks->current($page instanceof Page ? (int) $page->id : $page);
    }

    /**
     * A page's revisions, newest first (ADR-0038).
     *
     * @return list<array{id: int, revision: int, created_by: string|null, created_at: CarbonImmutable}>
     */
    public function revisions(Page|int $page): array
    {
        return $this->revisionStore->of($page instanceof Page ? (int) $page->id : $page);
    }

    /**
     * A revision's values by language code ("*" for fields that are not translatable).
     *
     * @return array<string, array<string, mixed>>
     */
    public function revisionValues(int $revision): array
    {
        return $this->revisionStore->find($revision)['values'] ?? [];
    }

    /**
     * A revision's values as pages.save takes them, for comparing with the page or restoring.
     *
     * @return array<string, mixed>
     */
    public function revisionInput(Page $page, int $revision): array
    {
        return $this->revisionStore->saveInput($page, $this->revisionValues($revision));
    }

    /**
     * Which of these pages have a draft, in one query (ADR-0039).
     *
     * @param  list<int>  $pageIds
     * @return list<int>
     */
    public function withDrafts(array $pageIds): array
    {
        return $this->drafts->among($pageIds);
    }

    /** The page's draft, or null (ADR-0039). */
    public function draft(Page|int $page): ?Page
    {
        $draft = $this->drafts->of($page instanceof Page ? (int) $page->id : $page);

        return $draft === null ? null : $this->loader->find($draft);
    }

    /**
     * Save values to the page's draft, as pages.save takes them.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveDraft(Page|int|string $page, array $values, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(SaveDraft::class, ['page' => $this->ref($page), 'values' => $values], $dryRun)->valueOrDryRun(Page::class);
    }

    public function publishDraft(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(PublishDraft::class, ['page' => $this->ref($page)], $dryRun)->valueOrDryRun(Page::class);
    }

    public function discardDraft(Page|int|string $page, bool $dryRun = false): ?ActionResult
    {
        $result = $this->runner->run(DiscardDraft::class, ['page' => $this->ref($page)], $dryRun);

        return $result->dryRun ? $result : null;
    }
}
