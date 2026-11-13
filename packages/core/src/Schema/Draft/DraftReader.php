<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a draft file (ADR-0116), refusing one that is missing or isn't YAML with a message that
 * says what to do.
 *
 * @api
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
            $draft = Yaml::parseFile($path);
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
            return $this->asDraft(Yaml::parse($yaml), 'the draft');
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

        return $draft;
    }
}
