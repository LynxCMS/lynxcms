<?php

declare(strict_types=1);

namespace LynxCms\Insights\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LynxCms\Insights\InsightsTables;
use Override;

/**
 * One report: the period it looked at, the sources that failed, and its insights. A module system
 * table, written only through insights.generate.
 *
 * @property int $id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property list<array{source: string, error: string}> $failures
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @internal
 */
final class InsightReport extends Model
{
    #[Override]
    protected $table = InsightsTables::REPORTS;

    #[Override]
    protected $guarded = [];

    /** @return HasMany<StoredInsight, $this> */
    public function insights(): HasMany
    {
        return $this->hasMany(StoredInsight::class, 'report_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'failures' => 'array'];
    }
}
