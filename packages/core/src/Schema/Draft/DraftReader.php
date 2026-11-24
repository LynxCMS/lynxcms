<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use DateTimeInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a draft file (ADR-0116), refusing one that is missing or isn't YAML with a message that
 * says what to do.
 *
 * @internal
 */
final readonly class DraftReader
{
    /**
     * @return array<array-key, mixed>
     *
     * @throws DraftError
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new DraftError("There is no draft at {$path}. Write a starting one with php artisan lynx:draft --stub.");
        }

        try {
            $draft = Yaml::parseFile($path, Yaml::PARSE_DATETIME);
        } catch (ParseException $e) {
            throw new DraftError("The draft at {$path} is not valid YAML: {$e->getMessage()}", $e->getCode(), previous: $e);
        }

        return $this->asDraft($draft, $path);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function parse(string $yaml): array
    {
        try {
            return $this->asDraft(Yaml::parse($yaml, Yaml::PARSE_DATETIME), 'the draft');
        } catch (ParseException $e) {
            throw new DraftError("The draft is not valid YAML: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function asDraft(mixed $draft, string $where): array
    {
        if ($draft === null) {
            return [];
        }

        if (! is_array($draft)) {
            throw new DraftError("A draft is a map of fields and templates; {$where} is not.");
        }

        return self::withDatesAsText($draft);
    }

    /**
     * YAML reads 2026-12-01 as a date; without PARSE_DATETIME it would arrive as a Unix timestamp.
     * A date becomes "2026-12-01", and a date with a time its ISO 8601 text.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function withDatesAsText(array $values): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            $value instanceof DateTimeInterface => $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format(DateTimeInterface::ATOM),
            is_array($value) => self::withDatesAsText($value),
            default => $value,
        }, $values);
    }
}
