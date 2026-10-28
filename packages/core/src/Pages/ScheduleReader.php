<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Read side of scheduled publishing (ADR-0036).
 *
 * @internal
 */
final readonly class ScheduleReader
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @return list<array{language: string, action: string, at: CarbonImmutable, error: string|null}>
     */
    public function of(int $pageId): array
    {
        $codes = $this->languages->all()->mapWithKeys(fn ($language): array => [(int) $language->id => (string) $language->code])->all();
        $entries = [];

        foreach (DB::table(Tables::SCHEDULES)->where('page_id', $pageId)->orderBy('language_id')->orderBy('action')->get() as $row) {
            $entries[] = [
                'language' => Value::int($row->language_id) === SchedulePage::WHOLE_PAGE ? SchedulePage::ALL_LANGUAGES : ($codes[Value::int($row->language_id)] ?? ''),
                'action' => Value::string($row->action),
                'at' => CarbonImmutable::parse(Value::string($row->at)),
                'error' => Value::nullableString($row->error),
            ];
        }

        return $entries;
    }
}
