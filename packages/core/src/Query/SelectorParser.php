<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

/**
 * Parses ProcessWire-style selectors:
 *
 *   template=course, price<300, title%=Delivery, sort=-title, limit=10
 *
 * Clauses are separated by commas; "|" separates alternative values (OR); values may be quoted
 * with " or ' to include commas or pipes.
 *
 * @api
 */
final class SelectorParser
{
    /**
     * @return list<Clause>
     */
    public function parse(string $selector): array
    {
        // SQLite ends bound strings at a NUL byte, which would silently change what a value means.
        if (str_contains($selector, "\0")) {
            throw new SelectorException('Selectors cannot contain NUL bytes.');
        }

        $clauses = [];

        foreach ($this->split($selector) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $clauses[] = $this->clause($part);
        }

        return $clauses;
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
     * Split on commas outside quotes.
     *
     * @return list<string>
     */
    private function split(string $selector): array
    {
        $parts = [];
        $current = '';
        $quote = null;

        foreach (mb_str_split($selector) as $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
                $current .= $char;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
            } elseif ($char === ',') {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $parts[] = $current;

        return $parts;
    }
}
