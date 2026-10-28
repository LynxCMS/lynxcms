<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;
use Traversable;

/**
 * An ordered list of pages, as returned by find() and page-reference fields.
 *
 * @implements IteratorAggregate<int, Page>
 *
 * @api
 */
final class PageArray implements Countable, IteratorAggregate, JsonSerializable
{
    private ?int $total;

    /**
     * @param  list<Page>  $pages
     * @param  int|(Closure(): int)|null  $total  total ignoring limit/start, computed lazily when a closure
     */
    public function __construct(private readonly array $pages, int|Closure|null $total = null)
    {
        $this->total = is_int($total) ? $total : null;
        $this->totalResolver = $total instanceof Closure ? $total : null;
    }

    /** @var (Closure(): int)|null */
    private readonly ?Closure $totalResolver;

    public function first(): ?Page
    {
        return $this->pages[0] ?? null;
    }

    public function last(): ?Page
    {
        return $this->pages === [] ? null : $this->pages[count($this->pages) - 1];
    }

    /**
     * @return list<Page>
     */
    public function all(): array
    {
        return $this->pages;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(fn (Page $p): int => (int) $p->id, $this->pages));
    }

    /** Total matches ignoring limit/start, when the query computed it. */
    public function total(): int
    {
        if ($this->total === null && $this->totalResolver !== null) {
            $this->total = ($this->totalResolver)();
        }

        return $this->total ?? count($this->pages);
    }

    public function isEmpty(): bool
    {
        return $this->pages === [];
    }

    /**
     * @template T
     *
     * @param  callable(Page): T  $callback
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->pages);
    }

    /**
     * The pages the callback keeps, in order; total() counts only them.
     *
     * @param  callable(Page): bool  $callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_values(array_filter($this->pages, $callback)));
    }

    /**
     * The pages published in a language, the current one by default (ADR-0093). Lists keep a page
     * that is offline in the visitor's language and link it to its default-language URL; this
     * drops it, for sections that feature pages per market. Pages without a URL show in every
     * language, so they are kept.
     */
    public function publishedIn(Language|string|null $language = null): self
    {
        $language ??= lynx()->languages->current();

        return $this->filter(fn (Page $page): bool => ! $page->template()->hasUrl() || $page->isActiveIn($language));
    }

    /**
     * One value per page as text, joined, like ProcessWire's implode(): a field or property name
     * ("title", "name", "url"), or a callback. Values are cast to strings, so field values join as
     * their stored text, which Laravel's Collection::implode() would not do (ADR-0070).
     *
     * @param  string|callable(Page): mixed  $property
     */
    public function implode(string $glue, string|callable $property = 'title'): string
    {
        $read = is_string($property) ? fn (Page $page): mixed => $page->{$property} : $property;

        return implode($glue, array_map(fn (Page $page): string => Value::string($read($page)), $this->pages));
    }

    public function count(): int
    {
        return count($this->pages);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->pages);
    }

    public function jsonSerialize(): mixed
    {
        return array_map(fn (Page $p): array => $p->toArray(), $this->pages);
    }
}
