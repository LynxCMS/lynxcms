<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * A plural reducer written as word endings (ADR-0119): the exceptions first, then the longest
 * ending that matches, and only that one. Words under MIN_LENGTH letters are never reduced. Extend
 * it for a new language, or extend a built-in reducer and add to parent::endings() or
 * parent::exceptions() for a regional one.
 *
 * @api
 */
abstract class EndingRules implements WordReducer
{
    /** Shorter words are left alone, so typing "mes" does not also find "me". */
    public const int MIN_LENGTH = 4;

    /** @var list<array{string, ?string}>|null */
    private ?array $sorted = null;

    /**
     * Ending => what replaces it; null keeps the word as it is.
     *
     * @return array<string, ?string>
     */
    abstract protected function endings(): array;

    /**
     * Words the endings get wrong, mapped to their singular (or to themselves).
     *
     * @return array<string, string>
     */
    abstract protected function exceptions(): array;

    public function reduce(string $word): string
    {
        if (mb_strlen($word) < self::MIN_LENGTH) {
            return $word;
        }

        $exception = $this->exceptions()[$word] ?? null;

        if ($exception !== null) {
            return $exception;
        }

        foreach ($this->sortedEndings() as [$ending, $replacement]) {
            if (str_ends_with($word, $ending)) {
                return $replacement === null ? $word : substr($word, 0, -strlen($ending)).$replacement;
            }
        }

        return $word;
    }

    /** @return list<array{string, ?string}> the endings, longest first */
    private function sortedEndings(): array
    {
        if ($this->sorted === null) {
            $endings = [];

            foreach ($this->endings() as $ending => $replacement) {
                $endings[] = [(string) $ending, $replacement];
            }

            usort($endings, fn (array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));
            $this->sorted = $endings;
        }

        return $this->sorted;
    }
}
