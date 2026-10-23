<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's translation loader, with JSON strings widened like TranslationLocales widens PHP
 * files: a language set to "pt-PT" reads lang/pt.json, then lang/pt_PT.json, then
 * lang/pt-PT.json, the most specific winning. Laravel looks up JSON strings for the exact locale
 * only, so without it __('Book now') stays English on a pt-PT page even with lang/pt.json there.
 *
 * @internal
 */
final readonly class RegionalJsonLoader implements Loader
{
    public function __construct(private Loader $loader) {}

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        if ($group !== '*' || $namespace !== '*') {
            return $this->loader->load($locale, $group, $namespace);
        }

        $lines = [];

        foreach (array_reverse(TranslationLocales::expand([$locale])) as $candidate) {
            $lines = [...$lines, ...$this->loader->load($candidate, '*', '*')];
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }

    /** Anything else FileLoader offers (jsonPaths(), addPath()…), for code that calls it. */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }
}
