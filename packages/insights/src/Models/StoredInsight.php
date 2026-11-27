<?php

declare(strict_types=1);

namespace LynxCms\Insights\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightsTables;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\InsightText;
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
 * @property InsightText $title
 * @property InsightText $reason
 * @property list<int> $pages
 * @property list<array{label: InsightText, value: string|int|float}> $evidence
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

    /**
     * Stored as a key and replacements, read in the current locale where it is shown.
     *
     * @return Attribute<InsightText, InsightText|string>
     */
    protected function title(): Attribute
    {
        return $this->text();
    }

    /** @return Attribute<InsightText, InsightText|string> */
    protected function reason(): Attribute
    {
        return $this->text();
    }

    /** @return Attribute<list<array{label: InsightText, value: string|int|float}>, list<array{label: InsightText|string, value: string|int|float}>> */
    protected function evidence(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $stored): array => array_values(array_map(
                fn (mixed $fact): array => ['label' => InsightText::fromArray(Value::array(Value::array($fact)['label'] ?? [])), 'value' => $this->scalar(Value::array($fact)['value'] ?? '')],
                $this->decode($stored),
            )),
            set: $this->encodeEvidence(...),
        )->shouldCache();
    }

    /**
     * @param  list<array{label: InsightText|string, value: string|int|float}>  $facts
     */
    private function encodeEvidence(array $facts): string
    {
        return json_encode(array_map(fn (array $fact): array => ['label' => InsightText::from($fact['label'])->toArray(), 'value' => $fact['value']], $facts), JSON_THROW_ON_ERROR);
    }

    /** @return Attribute<InsightText, InsightText|string> */
    private function text(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $stored): InsightText => InsightText::fromArray($this->decode($stored)),
            set: fn (InsightText|string $text): string => json_encode(InsightText::from($text)->toArray(), JSON_THROW_ON_ERROR),
        )->shouldCache();
    }

    /** @return array<mixed> */
    private function decode(mixed $stored): array
    {
        return Value::array(json_decode(Value::string($stored, '[]'), true));
    }

    private function scalar(mixed $value): string|int|float
    {
        return is_int($value) || is_float($value) ? $value : Value::string($value);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'plan' => 'array',
            'basis' => 'array',
            'weight' => 'float',
            'state' => InsightState::class,
            'carried_from' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
