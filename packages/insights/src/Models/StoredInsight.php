<?php

declare(strict_types=1);

namespace LynxCms\Insights\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LynxCms\Insights\InsightsTables;
use LynxCms\Insights\InsightState;
use Override;

/**
 * An insight as a report stored it (ADR-0155). A module system table, written only through the
 * insights actions.
 *
 * @property int $id
 * @property int $report_id
 * @property string $source
 * @property string $fingerprint
 * @property string $kind
 * @property string $title
 * @property string $reason
 * @property list<int> $pages
 * @property list<array{label: string, value: string|int|float}> $evidence
 * @property list<array{action: string, arguments: array<string, mixed>}> $plan
 * @property array<int|string, string|null> $basis page id => when it was last changed
 * @property float $weight
 * @property InsightState $state
 * @property int|null $carried_from
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @internal
 */
final class StoredInsight extends Model
{
    #[Override]
    protected $table = InsightsTables::INSIGHTS;

    #[Override]
    protected $guarded = [];

    /** @return BelongsTo<InsightReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(InsightReport::class, 'report_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'evidence' => 'array',
            'plan' => 'array',
            'basis' => 'array',
            'weight' => 'float',
            'state' => InsightState::class,
            'carried_from' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
