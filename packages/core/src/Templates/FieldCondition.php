<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use InvalidArgumentException;
use LynxCms\Core\Support\Value;

/**
 * A show_if or required_if condition (ADR-0030), written like ProcessWire's showIf:
 * "format=onsite|hybrid, price>0". Clauses separated by commas must all match; "|" separates
 * alternatives; an empty value means empty ("summary=" or "summary!="). Operators: = != > < >= <=
 * and %= (contains, any case). Numbers compare as numbers, toggles as 1 and 0, page references by id.
 *
 * @api
 */
final readonly class FieldCondition
{
    private const string CLAUSE = '/^\s*([a-z][a-z0-9_]*)\s*(!=|>=|<=|%=|=|>|<)\s*(.*?)\s*$/';

    /**
     * @param  list<array{field: string, operator: string, values: list<string>}>  $clauses
     */
    private function __construct(private array $clauses) {}

    public static function parse(string $condition): self
    {
        $clauses = [];

        foreach (explode(',', $condition) as $part) {
            if (preg_match(self::CLAUSE, $part, $match) !== 1 || str_starts_with($match[3], '=')) {
                throw new InvalidArgumentException("Cannot read the condition [{$condition}]: write it as field=value, for example format=online|hybrid.");
            }

            if ($match[3] === '' && ! in_array($match[2], ['=', '!='], true)) {
                throw new InvalidArgumentException("Cannot read the condition [{$condition}]: [{$match[2]}] needs a value.");
            }

            $clauses[] = ['field' => $match[1], 'operator' => $match[2], 'values' => explode('|', $match[3])];
        }

        return new self($clauses);
    }

    /**
     * @return list<string> the fields the condition reads, each once
     */
    public function fields(): array
    {
        return array_values(array_unique(array_column($this->clauses, 'field')));
    }

    /**
     * @param  callable(string): mixed  $valueOf  a field's value (shared, or in the default language)
     */
    public function matches(callable $valueOf): bool
    {
        return array_all($this->clauses, fn (array $clause): bool => $this->clauseMatches($clause, $valueOf($clause['field'])));
    }

    /**
     * @param  array{field: string, operator: string, values: list<string>}  $clause
     */
    private function clauseMatches(array $clause, mixed $value): bool
    {
        $actual = $this->scalars($value);
        $isAnyEqual = array_any($clause['values'], fn (string $expected): bool => $this->equals($actual, $expected));

        return match ($clause['operator']) {
            '=' => $isAnyEqual,
            '!=' => ! $isAnyEqual,
            '%=' => array_any($clause['values'], fn (string $expected): bool => array_any($actual, fn (string $item): bool => str_contains(mb_strtolower($item), mb_strtolower($expected)))),
            default => array_any($clause['values'], fn (string $expected): bool => array_any($actual, fn (string $item): bool => $this->compare($item, $clause['operator'], $expected))),
        };
    }

    /**
     * A value as the list of texts it holds: [] when empty, ids for page references, 1 or 0 for toggles.
     *
     * @return list<string>
     */
    private function scalars(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $texts = [];

        foreach ($items as $item) {
            $text = is_bool($item) ? ($item ? '1' : '0') : (is_scalar($item) ? (string) $item : Value::string($item));

            if (trim($text) !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param  list<string>  $actual
     */
    private function equals(array $actual, string $expected): bool
    {
        if ($expected === '') {
            return $actual === [];
        }

        return array_any($actual, fn (string $item): bool => is_numeric($item) && is_numeric($expected) ? (float) $item === (float) $expected : $item === $expected);
    }

    private function compare(string $actual, string $operator, string $expected): bool
    {
        if (! is_numeric($actual) || ! is_numeric($expected)) {
            return false;
        }

        $actual = (float) $actual;
        $expected = (float) $expected;

        return match ($operator) {
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            default => $actual <= $expected,
        };
    }
}
