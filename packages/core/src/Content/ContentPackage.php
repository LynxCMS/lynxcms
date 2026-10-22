<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;
use RuntimeException;

/**
 * Reads a content package directory written by ContentExporter (ADR-0040).
 *
 * @internal
 */
final readonly class ContentPackage
{
    private function __construct(public string $directory) {}

    public static function open(string $directory): self
    {
        $manifest = is_file($directory.'/manifest.json') ? Value::map(json_decode(File::get($directory.'/manifest.json'), true)) : [];

        if (($manifest['format'] ?? null) !== ContentExporter::FORMAT || ! is_file($directory.'/pages.jsonl')) {
            throw ValidationException::withMessages(['path' => "[{$directory}] is not a Lynx content package (no manifest.json and pages.jsonl)."]);
        }

        if (Value::int($manifest['version'] ?? 0) > ContentExporter::VERSION) {
            throw ValidationException::withMessages(['path' => 'The package was written by a newer Lynx (format version '.Value::int($manifest['version']).').']);
        }

        return new self($directory);
    }

    public function schemaDirectory(): string
    {
        return $this->directory.'/schema';
    }

    /**
     * The package's pages, parents first.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function pages(): Generator
    {
        $handle = fopen($this->directory.'/pages.jsonl', 'r') ?: throw new RuntimeException("Cannot read {$this->directory}/pages.jsonl.");

        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                yield Value::map(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
            }
        }

        fclose($handle);
    }
}
