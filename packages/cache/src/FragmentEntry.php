<?php

declare(strict_types=1);

namespace LynxCms\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * One stored fragment in the fragment cache's index (ADR-0064).
 *
 * @property int $id
 * @property string $key_hash
 * @property string $cache_key
 * @property string $name
 * @property string $language
 * @property string $audience
 * @property string $vary JSON list
 * @property string $depends_on ",page:12,template:course," or ",any,"
 * @property int $size bytes
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 *
 * @internal
 */
final class FragmentEntry extends Model
{
    public const string TABLE = 'lynx_fragment_cache';

    public const null UPDATED_AT = null;

    /** What a fragment without dependencies depends on: any change. */
    public const string ANY = 'any';

    #[Override]
    protected $table = self::TABLE;

    #[Override]
    protected $guarded = [];

    /**
     * @param  Builder<self>  $query
     * @param  list<string>  $dependencies
     */
    public static function dependingOn(Builder $query, array $dependencies): void
    {
        $query->where(function (Builder $query) use ($dependencies): void {
            foreach ($dependencies as $dependency) {
                $query->orWhere('depends_on', 'like', '%,'.$dependency.',%');
            }
        });
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return array_values(array_filter(explode(',', $this->depends_on)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
