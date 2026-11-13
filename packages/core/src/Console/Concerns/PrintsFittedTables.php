<?php

declare(strict_types=1);

namespace LynxCms\Core\Console\Concerns;

use Symfony\Component\Console\Terminal;

/**
 * Tables that fit the terminal. A long cell (a field every template uses) would make each line
 * wider than the window, which the terminal folds into an unreadable block, so the widest columns
 * wrap at word boundaries instead. --json keeps the full, unwrapped values.
 *
 * @internal
 */
trait PrintsFittedTables
{
    /** Narrower than this a column is unreadable, so a tiny terminal gets a wider table instead. */
    private const int MIN_COLUMN_WIDTH = 10;

    /** Each column adds "| " before and " " after its content; the row closes with "|". */
    private const int BORDER_WIDTH_PER_COLUMN = 3;

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    protected function fittedTable(array $headers, array $rows): void
    {
        $widths = $this->fittedWidths([$headers, ...$rows]);

        $this->table(
            $this->wrapCells($headers, $widths),
            array_map(fn (array $row): array => $this->wrapCells($row, $widths), $rows),
        );
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, int>  $widths
     * @return list<string>
     */
    private function wrapCells(array $cells, array $widths): array
    {
        $wrapped = [];

        foreach ($cells as $column => $cell) {
            $wrapped[] = wordwrap($cell, $widths[$column], "\n", true);
        }

        return $wrapped;
    }

    /**
     * Shares the terminal's width among the columns: narrow columns keep their width, and the
     * rest split what is left evenly.
     *
     * @param  non-empty-list<list<string>>  $lines
     * @return array<int, int>
     */
    private function fittedWidths(array $lines): array
    {
        $natural = $this->naturalWidths($lines);
        asort($natural);
        $left = (new Terminal)->getWidth() - self::BORDER_WIDTH_PER_COLUMN * count($natural) - 1;
        $columnsLeft = count($natural);
        $widths = [];

        foreach ($natural as $column => $width) {
            $widths[$column] = max(self::MIN_COLUMN_WIDTH, min($width, intdiv(max(0, $left), $columnsLeft)));
            $left -= $widths[$column];
            $columnsLeft--;
        }

        return $widths;
    }

    /**
     * @param  non-empty-list<list<string>>  $lines
     * @return array<int, int>
     */
    private function naturalWidths(array $lines): array
    {
        $widths = [];

        foreach ($lines as $cells) {
            foreach ($cells as $column => $cell) {
                $widths[$column] = max($widths[$column] ?? 0, mb_strwidth($cell));
            }
        }

        return $widths;
    }
}
