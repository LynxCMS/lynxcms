<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

/**
 * One template's structured data: its schema.org type and where each property's value comes
 * from. Sources are a field name, a subfield of a list property's repeater, a built-in such as
 * "@title", or a constant written "=EUR".
 *
 * @api
 */
final readonly class Mapping
{
    /** Built-in sources a page has whatever its template. */
    public const array BUILT_INS = ['@title', '@url', '@published', '@modified'];

    /** The site's organization or person from the SEO settings, for author, provider, organizer or worksFor. */
    public const string ORGANIZATION = '@organization';

    /**
     * @param  array<string, string>  $sources  property => source, in the order written
     */
    public function __construct(public string $template, public string $type, public array $sources) {}

    /**
     * Lines of "property: source". Blank lines and lines starting with # are skipped.
     *
     * @return array{0: array<string, string>, 1: list<string>} the sources, and the lines that are not "property: source"
     */
    public static function parse(string $lines): array
    {
        $sources = [];
        $invalid = [];

        foreach (preg_split('/\R/', $lines) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z][\w.]*)\s*:\s*(\S.*)$/', $line, $match) === 1) {
                $sources[$match[1]] = trim($match[2]);
            } else {
                $invalid[] = $line;
            }
        }

        return [$sources, $invalid];
    }

    public static function isConstant(string $source): bool
    {
        return str_starts_with($source, '=');
    }

    public static function isBuiltIn(string $source): bool
    {
        return str_starts_with($source, '@');
    }
}
