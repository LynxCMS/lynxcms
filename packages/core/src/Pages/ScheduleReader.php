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
        $codes = $this->codes();
        $rows = DB::table(Tables::SCHEDULES)->where('page_id', $pageId)->orderBy('language_id')->orderBy('action')->get();

        return array_values(array_map(function (object $row) use ($codes): array {
            $entry = $this->entry($row, $codes);
            unset($entry['page']);

            return $entry;
        }, $rows->all()));
    }

    /**
     * The next scheduled changes among the given pages, soonest first, failed ones included.
     *
     * @param  list<int>  $pageIds
     * @return list<array{page: int, language: string, action: string, at: CarbonImmutable, error: string|null}>
     */
    public function upcoming(array $pageIds, int $limit): array
    {
        if ($pageIds === []) {
            return [];
        }

        $codes = $this->codes();
        $rows = DB::table(Tables::SCHEDULES)->whereIn('page_id', $pageIds)->orderBy('at')->orderBy('id')->limit(max(1, $limit))->get();

        return array_values(array_map(fn (object $row): array => $this->entry($row, $codes), $rows->all()));
    }

    /** @return array<int, string> language id => code */
    private function codes(): array
    {
        return $this->languages->all()->mapWithKeys(fn ($language): array => [(int) $language->id => (string) $language->code])->all();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array{page: int, language: string, action: string, at: CarbonImmutable, error: string|null}
     */
    private function entry(object $row, array $codes): array
    {
        $languageId = Value::int($row->language_id ?? 0);

        return [
            'page' => Value::int($row->page_id ?? 0),
            'language' => $languageId === SchedulePage::WHOLE_PAGE ? SchedulePage::ALL_LANGUAGES : ($codes[$languageId] ?? ''),
            'action' => Value::string($row->action ?? ''),
            'at' => CarbonImmutable::parse(Value::string($row->at ?? '')),
            'error' => Value::nullableString($row->error ?? null),
        ];
    }
}
