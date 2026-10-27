<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Selector terms on a page's state rather than its fields (ADR-0090): "status=hidden|unpublished",
 * "language!=pt" (not published in Portuguese), "has_draft=1" and "has_schedule=1".
 *
 * @internal
 */
final readonly class PageStateMatch
{
    private const string PAGES = Tables::PAGES;

    /** The terms this class answers; "language" only with !=, since language=pt picks the language to match in. */
    public const array TERMS = ['status', 'has_draft', 'has_schedule'];

    /** Statuses a selector may name: the others (unique, system) are bookkeeping, not states editors set. */
    private const array STATUSES = ['hidden', 'unpublished', 'locked'];

    public function __construct(private LanguageRegistry $languages) {}

    public static function handles(Clause $clause): bool
    {
        return in_array($clause->field, self::TERMS, true)
            || (in_array($clause->field, ['language', 'lang'], true) && $clause->operator === '!=');
    }

    /**
     * Adds the clause's condition.
     *
     * @return list<string> the statuses "status=" names, which the status filter must then let through
     */
    public function apply(Builder $query, Clause $clause): array
    {
        $this->operator($clause);

        return match ($clause->field) {
            'status' => $this->status($query, $clause),
            'has_draft' => $this->exists($query, $clause, fn (Builder $q) => $q->from(self::PAGES.' as lynx_draft')
                ->whereColumn('lynx_draft.draft_of', self::PAGES.'.id')),
            'has_schedule' => $this->exists($query, $clause, fn (Builder $q) => $q->from(Tables::SCHEDULES)
                ->whereColumn(Tables::SCHEDULES.'.page_id', self::PAGES.'.id')
                ->whereNull(Tables::SCHEDULES.'.failed_at')),
            default => $this->notInLanguages($query, $clause),
        };
    }

    private function operator(Clause $clause): void
    {
        if (! in_array($clause->operator, ['=', '!='], true)) {
            throw new SelectorException("[{$clause->field}] takes = or !=, not [{$clause->operator}].");
        }
    }

    /**
     * Matches any of the named statuses (or none of them with !=). Listed rather than written as a
     * bitwise test, for the same reason as StatusFilter (ADR-0061).
     *
     * @return list<string>
     */
    private function status(Builder $query, Clause $clause): array
    {
        $names = array_values(array_map(fn (mixed $value): string => Value::string($value), $clause->values));
        $flags = 0;

        foreach ($names as $name) {
            if (! in_array($name, self::STATUSES, true)) {
                throw new SelectorException("Unknown status [{$name}]: use ".implode(', ', self::STATUSES).'.');
            }

            $flags |= PageStatus::NAMES[$name];
        }

        $matching = array_values(array_filter(range(0, array_sum(PageStatus::NAMES)), fn (int $status): bool => ($status & $flags) !== 0));
        $clause->operator === '!=' ? $query->whereNotIn(self::PAGES.'.status', $matching) : $query->whereIn(self::PAGES.'.status', $matching);

        return $clause->operator === '!=' ? [] : $names;
    }

    /**
     * "has_draft=1" and "has_draft=0" (or !=1): whether the subquery finds a row for the page.
     *
     * @param  Closure(Builder): Builder  $rows
     * @return list<string>
     */
    private function exists(Builder $query, Clause $clause, Closure $rows): array
    {
        $value = Value::string($clause->value());

        if (! in_array($value, ['0', '1'], true)) {
            throw new SelectorException("[{$clause->field}] takes 1 or 0, not [{$value}].");
        }

        $wanted = ($value === '1') !== ($clause->operator === '!=');
        $query->{$wanted ? 'whereExists' : 'whereNotExists'}(fn (Builder $q) => $rows($q->select(DB::raw(1))));

        return [];
    }

    /**
     * "language!=pt|de": pages published in none of the languages. Every page is in the default
     * language, so naming it matches nothing.
     *
     * @return list<string>
     */
    private function notInLanguages(Builder $query, Clause $clause): array
    {
        foreach ($clause->values as $code) {
            $language = $this->languages->find(Value::string($code))
                ?? throw new SelectorException('Unknown language ['.Value::string($code).'] in selector.');

            $this->notIn($query, $language);
        }

        return [];
    }

    private function notIn(Builder $query, Language $language): void
    {
        if ($language->isDefault()) {
            $query->whereNull(self::PAGES.'.id');

            return;
        }

        $query->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from(Tables::PAGE_LANGUAGES)
            ->whereColumn(Tables::PAGE_LANGUAGES.'.page_id', self::PAGES.'.id')
            ->where(Tables::PAGE_LANGUAGES.'.language_id', $language->id)
            ->where(Tables::PAGE_LANGUAGES.'.status', 'published'));
    }
}
