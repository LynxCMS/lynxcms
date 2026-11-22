<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Protection of locked and system pages (ADR-0027), checked by every page action against the
 * stored status, so a changed Page object cannot unlock itself.
 *
 * Locked: no edits, moves, sorting, template switches, translations or deletes; only the lock
 * itself may change. System: no deletes, moves, template switches or status changes.
 *
 * @internal
 */
final readonly class PageGuard
{
    /** Ids per query, below the bound parameter limits of SQLite and PostgreSQL. */
    private const int CHUNK = 1000;

    public function __construct(private EditLocks $editLocks, private ActionRunner $runner) {}

    /** Values, names and translations. */
    public function assertEditable(Page $page): void
    {
        $this->assertNotBeingEdited((int) $page->id);

        if ($this->has($this->stored($page), PageStatus::LOCKED)) {
            $this->fail(Value::string(__('lynx::validation.page.locked', ['page' => $page->name()])));
        }
    }

    /** Position among siblings. */
    public function assertSortable(Page $page): void
    {
        $this->assertEditable($page);
    }

    /** Parent and template. $change names the refusal: "moved" or "switched" (to another template). */
    public function assertMovable(Page $page, string $change = 'moved'): void
    {
        $this->assertEditable($page);

        if ($this->has($this->stored($page), PageStatus::SYSTEM)) {
            $this->fail(Value::string(__("lynx::validation.page.system.{$change}", ['page' => $page->name()])));
        }
    }

    /**
     * The flags a status change touches: locked pages only change the lock, system pages nothing.
     */
    public function assertStatusChange(Page $page, int $flags): void
    {
        $this->assertNotBeingEdited((int) $page->id);

        $stored = $this->stored($page);

        if ($this->has($stored, PageStatus::SYSTEM)) {
            $this->fail(Value::string(__('lynx::validation.page.system.status', ['page' => $page->name()])));
        }

        if ($this->has($stored, PageStatus::LOCKED) && ($flags & ~PageStatus::LOCKED) !== 0) {
            $this->fail(Value::string(__('lynx::validation.page.locked', ['page' => $page->name()])));
        }
    }

    /**
     * A page and everything below it, before a delete or a move to the trash. The refusal names
     * the page that blocks, so it can be found and unlocked (ADR-0035).
     *
     * @param  list<int>  $ids
     */
    public function assertDeletable(Page $page, array $ids): void
    {
        $this->assertNotBeingEdited(...$ids);

        $statuses = [];

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $statuses += array_map(Value::int(...), DB::table(Tables::PAGES)->whereIn('id', $chunk)->pluck('status', 'id')->all());
        }

        ksort($statuses);

        foreach ([PageStatus::LOCKED => 'locked', PageStatus::SYSTEM => 'system'] as $flag => $reason) {
            foreach ($statuses as $id => $status) {
                if ($this->has($status, $flag)) {
                    $this->fail($this->undeletable($page, (int) $id, $reason));
                }
            }
        }
    }

    /** Names the page that blocks, and the page acted on when it is another, above it. */
    private function undeletable(Page $page, int $blockingId, string $reason): string
    {
        if ($blockingId === $page->id) {
            return Value::string(__("lynx::validation.page.delete.{$reason}", ['page' => $page->name()]));
        }

        $name = Value::string(DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $blockingId)->orderBy('language_id')->value('name'));

        return Value::string(__("lynx::validation.page.delete.{$reason}_below", ['page' => $name, 'above' => $page->name()]));
    }

    /** Someone else has the page open in an editor (ADR-0037). New pages have no id yet. */
    private function assertNotBeingEdited(int ...$pageIds): void
    {
        $this->editLocks->assertFree($this->runner->actor(), ...array_filter($pageIds, fn (int $id): bool => $id > 0));
    }

    /** On update, the system flag stays as stored: a save can neither add nor remove it. */
    public function keepSystemFlag(Page $page): void
    {
        $page->status = ($page->status & ~PageStatus::SYSTEM) | ($this->stored($page) & PageStatus::SYSTEM);
    }

    private function stored(Page $page): int
    {
        return $page->id === null ? 0 : Value::int(DB::table(Tables::PAGES)->where('id', $page->id)->value('status'));
    }

    private function has(int $status, int $flag): bool
    {
        return ($status & $flag) === $flag;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['page' => $message]);
    }
}
