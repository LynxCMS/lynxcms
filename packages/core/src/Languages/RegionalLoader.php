<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's translation loader, widened like TranslationLocales: a language set to "pt-PT" reads
 * lang/pt, then lang/pt_PT, then lang/pt-PT, the most specific winning key by key. That covers
 * JSON strings, which Laravel looks up for the exact locale only, so __('Book now') stays English
 * on a pt-PT page even with lang/pt.json there; and PHP files, which trans_choice() checks for the
 * exact locale before picking plural forms, so it showed English plurals with lang/pt/rooms.php.
 *
 * @internal
 */
final readonly class RegionalLoader implements Loader
{
    public function __construct(private Loader $loader) {}

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = [];

        foreach (array_reverse(TranslationLocales::expand([$locale])) as $candidate) {
            $lines = array_replace_recursive($lines, $this->loader->load($candidate, $group, $namespace));
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

    /**
     * Anything else FileLoader offers (jsonPaths(), addPath()…), for code that calls it.
     *
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }
}
