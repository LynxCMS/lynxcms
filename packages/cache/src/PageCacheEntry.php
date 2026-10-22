<?php

declare(strict_types=1);

namespace LynxCms\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LynxCms\Cache\Pages\CacheAddress;
use Override;

/**
 * One stored page in the page cache's index (ADR-0064).
 *
 * @property int $id
 * @property string $key_hash
 * @property string $host
 * @property string $path
 * @property string $query
 * @property int $page_id
 * @property int|null $parent_id
 * @property string $ancestor_ids comma separated, nearest first
 * @property int $language_id
 * @property string $template
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 *
 * @internal
 */
final class PageCacheEntry extends Model
{
    public const string TABLE = 'lynx_page_cache';

    public const null UPDATED_AT = null;

    #[Override]
    protected $table = self::TABLE;

    #[Override]
    protected $guarded = [];

    public function address(): CacheAddress
    {
        return new CacheAddress($this->host, $this->path, $this->query);
    }

    /** @return list<int> */
    public function ancestorIds(): array
    {
        return $this->ancestor_ids === '' ? [] : array_map(intval(...), explode(',', $this->ancestor_ids));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
