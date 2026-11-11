<?php

declare(strict_types=1);

namespace LynxCms\Finder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Finder\FinderTables;
use Override;

/**
 * A finder search kept under a name (ADR-0111), for the user who saved it or, shared, for everyone
 * who may use the finder. A module system table, written only through finder.save_search and
 * finder.delete_search.
 *
 * @property int $id
 * @property string $name
 * @property string $owner
 * @property bool $is_shared
 * @property array<string, mixed> $query
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @api
 */
final class SavedSearch extends Model
{
    #[Override]
    protected $table = FinderTables::SEARCHES;

    #[Override]
    protected $guarded = [];

    /** How an actor is stored as an owner: "user:5". */
    public static function ownerKey(Actor $actor): string
    {
        return $actor->kind.($actor->id !== null ? ':'.$actor->id : '');
    }

    /**
     * The searches $owner saved and the shared ones, by name.
     *
     * @return Builder<self>
     */
    public static function visibleTo(string $owner): Builder
    {
        return self::query()
            ->where(fn (Builder $query): Builder => $query->where('owner', $owner)->orWhere('is_shared', true))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['is_shared' => 'boolean', 'query' => 'array'];
    }
}
