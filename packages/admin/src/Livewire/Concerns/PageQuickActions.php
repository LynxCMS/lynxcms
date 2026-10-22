<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Closure;
use Livewire\Attributes\Locked;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * Status toggles, trash and delete-with-confirmation for the page tree's row actions. In the
 * site's tree, Delete moves pages to the trash and deletes trashed branches for good (ADR-0035);
 * trees rooted elsewhere (a menu) delete directly.
 *
 * @internal
 */
trait PageQuickActions
{
    #[Locked]
    public ?int $deleting = null;

    #[Locked]
    public int $deleteCount = 0;

    /**
     * What the last Empty trash did, for the report above the tree.
     *
     * @var array{deleted: int, blocked: list<array{title: string, locked: list<array{id: int, title: string}>}>, denied: int}|null
     */
    #[Locked]
    public ?array $trashReport = null;

    /** Flip one editable flag: hidden, unpublished or locked. */
    public function toggleStatus(int $id, string $flag): void
    {
        $bit = PageStatus::NAMES[$flag] ?? 0;

        $this->attempt(function () use ($id, $flag, $bit): void {
            $page = $this->movablePage($id);

            if ($bit !== 0) {
                lynx()->pages->setStatus($id, ...($page->hasStatus($bit) ? ['remove' => [$flag]] : ['add' => [$flag]]));
            }
        });
    }

    /** Ask before trashing or deleting, with the number of pages below from a dry run. */
    public function confirmDelete(int $id): void
    {
        $this->attempt(function () use ($id): void {
            $this->movablePage($id);
            $result = $this->isTrashed($id) ? lynx()->pages->delete($id, dryRun: true)
                : ($this->usesTrash() ? lynx()->pages->trash($id, dryRun: true) : lynx()->pages->delete($id, dryRun: true));
            $ids = [];

            // The change listing the branch; a first trash also records creating the trash page.
            foreach ($result instanceof ActionResult ? $result->changes->all() : [] as $change) {
                $ids = $ids === [] ? Value::array($change->data['ids'] ?? []) : $ids;
            }

            $this->deleteCount = max(0, count($ids) - 1);
            $this->deleting = $id;
        });
    }

    public function cancelDelete(): void
    {
        $this->deleting = null;
    }

    public function delete(int $id): void
    {
        if ($this->deleting !== $id) {
            return;
        }

        $this->attempt(function () use ($id): void {
            $this->movablePage($id);

            match (true) {
                $this->isTrashed($id) => $this->report(lynx()->pages->emptyTrash($id)),
                $this->usesTrash() => lynx()->pages->trash($id),
                default => lynx()->pages->delete($id),
            };
        });
        $this->deleting = null;
    }

    /** Put a trashed branch back where it was. */
    public function restore(int $id): void
    {
        $this->attempt(function () use ($id): void {
            $this->movablePage($id);
            lynx()->pages->restore($id);
        });
    }

    /** Delete everything in the trash that may go, and report what stayed and why. */
    public function emptyTrash(): void
    {
        $this->attempt(fn () => $this->report(lynx()->pages->emptyTrash()));
    }

    public function closeTrashReport(): void
    {
        $this->trashReport = null;
    }

    /**
     * @param  array{deleted: list<int>, blocked: list<array{page: int, locked: list<int>}>, denied: list<int>}|ActionResult  $result
     */
    private function report(array|ActionResult $result): void
    {
        if ($result instanceof ActionResult) {
            return;
        }

        $title = fn (int $id): string => Value::string(lynx()->pages->get($id)?->get('title')) ?: (string) lynx()->pages->get($id)?->name();

        $this->trashReport = [
            'deleted' => count($result['deleted']),
            'blocked' => array_map(fn (array $branch): array => [
                'title' => $title($branch['page']),
                'locked' => array_map(fn (int $id): array => ['id' => $id, 'title' => $title($id)], $branch['locked']),
            ], $result['blocked']),
            'denied' => count($result['denied']),
        ];
    }

    /** The site's tree trashes; a tree rooted elsewhere (a menu) deletes directly. */
    private function usesTrash(): bool
    {
        return $this->rootPage()->parentId === null;
    }

    /** The top page of a trashed branch. */
    private function isTrashed(int $id): bool
    {
        $trash = lynx()->pages->trashId();

        return $trash !== null && lynx()->pages->get($id)?->parentId === $trash;
    }

    /** Run a write; a validation failure becomes the visible error instead of a 500. */
    abstract protected function attempt(Closure $write): void;

    /** The page, when it may be changed from this tree. */
    abstract protected function movablePage(int $id): Page;

    abstract protected function rootPage(): Page;
}
