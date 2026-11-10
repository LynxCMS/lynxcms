<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

/**
 * Parses ProcessWire-style selectors:
 *
 *   template=course, price<300, title%=Delivery, sort=-title, limit=10
 *
 * Clauses are separated by commas; "|" separates alternative values (OR); values may be quoted
 * with " or ' to include commas or pipes. Parenthesised groups are OR alternatives (ADR-0110):
 * "(price<20), (price>70)" matches either, and "cost=(…), cost=(…)" names a set of them.
 *
 * @api
 */
final class SelectorParser
{
    /** Terms that act on the whole selector, so they have no meaning inside one alternative. */
    private const array WHOLE_SELECTOR = ['sort', 'limit', 'start', 'include', 'join', 'search'];

    /** A part that opens a named group: "cost=(". */
    private const string NAMED_GROUP = '/^\s*([a-z_][a-z0-9_]*)\s*=\s*\(/i';

    /**
     * The selector's clauses. A selector with OR groups needs parseSelector(), which keeps them.
     *
     * @return list<Clause>
     */
    public function parse(string $selector): array
    {
        $parsed = $this->parseSelector($selector);

        if ($parsed->groups !== []) {
            throw new SelectorException('This selector has OR groups: read it with SelectorParser::parseSelector().');
        }

        return $parsed->clauses;
    }

    public function parseSelector(string $selector): ParsedSelector
    {
        // SQLite ends bound strings at a NUL byte, which would silently change what a value means.
        if (str_contains($selector, "\0")) {
            throw new SelectorException('Selectors cannot contain NUL bytes.');
        }

        $clauses = [];
        $sets = [];

        foreach ($this->parts($selector) as $part) {
            $group = $this->group($part);

            if ($group === null) {
                $clauses[] = $this->clause($part);
            } else {
                $sets[$group[0]][] = $this->alternative($group[1]);
            }
        }

        $groups = [];

        foreach ($sets as $name => $alternatives) {
            $groups[] = new OrGroupSet((string) $name, $alternatives);
        }

        return new ParsedSelector($clauses, $groups);
    }

    /**
     * The non-empty parts between top-level commas.
     *
     * @return list<string>
     */
    private function parts(string $selector): array
    {
        return array_values(array_filter(array_map(trim(...), $this->split($selector)), fn (string $part): bool => $part !== ''));
    }

    /**
     * A group's name ("" when unnamed) and what its parentheses hold, or null for a plain clause.
     *
     * @return array{string, string}|null
     */
    private function group(string $part): ?array
    {
        $isNamed = preg_match(self::NAMED_GROUP, $part, $m) === 1;

        if (! $isNamed && ! str_starts_with($part, '(')) {
            return null;
        }

        if (! str_ends_with($part, ')')) {
            throw new SelectorException("Unbalanced parentheses in selector part [{$part}].");
        }

        $open = strpos($part, '(');

        return [$isNamed ? strtolower($m[1]) : '', substr($part, (int) $open + 1, -1)];
    }

    /**
     * One OR alternative: its clauses, all of which must match.
     *
     * @return list<Clause>
     */
    private function alternative(string $inner): array
    {
        $clauses = [];

        foreach ($this->parts($inner) as $part) {
            if ($this->group($part) !== null) {
                throw new SelectorException("OR groups cannot hold other groups: [{$part}].");
            }

            $clause = $this->clause($part);
            $isLanguage = in_array($clause->field, ['language', 'lang'], true) && $clause->operator !== '!=';

            if (in_array($clause->field, self::WHOLE_SELECTOR, true) || $isLanguage) {
                throw new SelectorException("[{$clause->field}] applies to the whole selector, so it cannot sit inside an OR group.");
            }

            $clauses[] = $clause;
        }

        return $clauses === [] ? throw new SelectorException('An OR group cannot be empty.') : $clauses;
    }

    private function clause(string $part): Clause
    {
        if (! preg_match('/^\s*([a-z_][a-z0-9_.]*(?:\|[a-z_][a-z0-9_.]*)*)\s*(!=|<=|>=|%=|\*=|~=|\^=|\$=|=|<|>)\s*(.*)$/si', $part, $m)) {
            throw new SelectorException("Cannot parse selector part [{$part}]. Expected field, operator and value, e.g. title%=Delivery.");
        }

        return new Clause(strtolower($m[1]), $m[2], $this->values($m[3]));
    }

    /**
     * @return list<string>
     */
    private function values(string $raw): array
    {
        $values = [];
        $current = '';
        $quote = null;
        $length = strlen($raw);

        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                } else {
                    $current .= $char;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '|') {
                $values[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $values[] = trim($current);

        return $values;
    }

    /**
     * Split on commas outside quotes and outside OR groups. A parenthesis opens a group only at
     * the start of a part ("(" or "name=("), so values such as "Risk (basics)" keep theirs.
     *
     * @return list<string>
     */
    private function split(string $selector): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $depth = 0;

        foreach (mb_str_split($selector) as $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' && ($depth > 0 || $this->opensGroup($current))) {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    /** Whether a parenthesis after what the part holds so far opens an OR group. */
    private function opensGroup(string $current): bool
    {
        return trim($current) === '' || preg_match(self::NAMED_GROUP, $current.'(') === 1;
    }
}
