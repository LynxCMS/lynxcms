<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Edit locks (ADR-0037): while someone edits a page, nobody else may save it. A lock lasts
 * lynx.edit_lock_seconds unless renewed; an expired lock is free. The console and the system
 * actor (scheduled publishing, imports) are not held back by them.
 *
 * @internal
 */
final class EditLocks
{
    /** Who acts, as stored: "user:5", "mcp", "ai:5". */
    public static function holder(Actor $actor): string
    {
        return $actor->kind.($actor->id === null ? '' : ':'.$actor->id);
    }

    /**
     * The page's lock while it lasts.
     *
     * @return array{holder: string, acquired_at: CarbonImmutable, expires_at: CarbonImmutable}|null
     */
    public function current(int $pageId): ?array
    {
        $row = DB::table(Tables::EDIT_LOCKS)->where('page_id', $pageId)->where('expires_at', '>', now())->first();

        return $row === null ? null : [
            'holder' => Value::string($row->holder),
            'acquired_at' => CarbonImmutable::parse(Value::string($row->acquired_at)),
            'expires_at' => CarbonImmutable::parse(Value::string($row->expires_at)),
        ];
    }

    /** Refuses when someone else holds a lock on any of the pages. */
    public function assertFree(Actor $actor, int ...$pageIds): void
    {
        if (in_array($actor->kind, [Actor::CLI, Actor::SYSTEM], true) || $pageIds === []) {
            return;
        }

        $row = DB::table(Tables::EDIT_LOCKS)->whereIn('page_id', $pageIds)->where('expires_at', '>', now())
            ->where('holder', '!=', self::holder($actor))->first();

        if ($row !== null) {
            $name = Value::string(DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $row->page_id)->orderBy('language_id')->value('name'));

            throw ValidationException::withMessages(['page' => "Page [{$name}] is being edited by ".Value::string($row->holder).' until '.CarbonImmutable::parse(Value::string($row->expires_at))->format('H:i').'. Try again when they are done.']);
        }
    }

    /**
     * @return array{holder: string, acquired_at: CarbonImmutable, expires_at: CarbonImmutable}
     */
    public function acquire(Actor $actor, int $pageId): array
    {
        $holder = self::holder($actor);
        $current = $this->current($pageId);
        $acquired = $current !== null && $current['holder'] === $holder ? $current['acquired_at'] : CarbonImmutable::now();
        $expires = CarbonImmutable::now()->addSeconds(max(1, Value::int(config('lynx.edit_lock_seconds'), 120)));

        DB::table(Tables::EDIT_LOCKS)->updateOrInsert(['page_id' => $pageId], ['holder' => $holder, 'acquired_at' => $acquired, 'expires_at' => $expires]);

        return ['holder' => $holder, 'acquired_at' => $acquired, 'expires_at' => $expires];
    }

    public function release(int $pageId): void
    {
        DB::table(Tables::EDIT_LOCKS)->where('page_id', $pageId)->delete();
    }
}
