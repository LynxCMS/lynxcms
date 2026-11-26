<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;

/**
 * Which submissions the inbox shows and its bulk actions and export act on: a form, a status
 * ("open" is new and read, "all" every status), text in the answers, a date range, an email
 * address that is a whole answer (for a person's data, ADR-0147), and the newest id when the
 * selection was made, so a submission that arrives later is left alone.
 *
 * @api
 */
final readonly class SubmissionFilter
{
    /** What the status filter takes, besides Submission::STATUSES. */
    public const array SHOWS = ['open', 'all', ...Submission::STATUSES];

    public function __construct(
        public string $show = 'open',
        public string $form = '',
        public string $search = '',
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $upTo = null,
        public string $email = '',
    ) {}

    /**
     * From an action's or a request's input: show, form, search, from and to (Y-m-d), up_to, email.
     *
     * @param  array<array-key, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $date = fn (string $key): ?CarbonImmutable => self::day($input[$key] ?? null);
        $show = Value::string($input['show'] ?? 'open');

        return new self(
            show: in_array($show, self::SHOWS, true) ? $show : 'open',
            form: Value::string($input['form'] ?? ''),
            search: Value::string($input['search'] ?? ''),
            from: $date('from')?->startOfDay(),
            to: $date('to')?->endOfDay(),
            upTo: isset($input['up_to']) ? Value::int($input['up_to']) : null,
            email: trim(Value::string($input['email'] ?? '')),
        );
    }

    /** A Y-m-d day, or null for anything else: the request's query reaches here as typed. */
    private static function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        // 2026-02-30 reads as 2 March: only a day that reads back the same is one.
        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $value ? $day : null;
    }

    /** @return array<string, string|int> the filter as fromArray() reads it, without empty values */
    public function toArray(): array
    {
        return array_filter([
            'show' => $this->show,
            'form' => $this->form,
            'search' => $this->search,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'up_to' => $this->upTo,
            'email' => $this->email,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function withUpTo(?int $id): self
    {
        return new self($this->show, $this->form, $this->search, $this->from, $this->to, $id, $this->email);
    }

    /** @return Builder<Submission> */
    public function query(): Builder
    {
        $query = Submission::query();

        match ($this->show) {
            'open' => $query->whereIn('status', ['new', 'read']),
            'all' => null,
            default => $query->where('status', $this->show),
        };

        $query->when($this->form !== '', fn (Builder $q) => $q->where('form', $this->form))
            ->when($this->from !== null, fn (Builder $q) => $q->where('created_at', '>=', $this->from))
            ->when($this->to !== null, fn (Builder $q) => $q->where('created_at', '<=', $this->to))
            ->when($this->upTo !== null, fn (Builder $q) => $q->where('id', '<=', $this->upTo));

        return $this->withEmail($this->searched($query));
    }

    /**
     * Submissions where the address is a whole answer: its JSON string, quotes included, so
     * "ana@example.com" doesn't match "joana@example.com". LIKE's wildcards are escaped, as
     * addresses often hold "_".
     *
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    private function withEmail(Builder $query): Builder
    {
        if ($this->email === '') {
            return $query;
        }

        $value = mb_strtolower(Value::string(json_encode($this->email)));
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);

        return $query->whereRaw("LOWER(CAST(data AS TEXT)) LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
    }

    /**
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    private function searched(Builder $query): Builder
    {
        $search = mb_strtolower(trim($this->search));

        if ($search === '') {
            return $query;
        }

        // Answers are JSON: match the text as typed and as JSON escapes it (ã → ã), with LIKE's wildcards escaped.
        $like = fn (string $text): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
        $escaped = mb_strtolower(trim(Value::string(json_encode($search)), '"'));

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("LOWER(CAST(data AS TEXT)) LIKE ? ESCAPE '!'", [$like($search)])
            ->orWhereRaw("LOWER(CAST(data AS TEXT)) LIKE ? ESCAPE '!'", [$like($escaped)]));
    }
}
