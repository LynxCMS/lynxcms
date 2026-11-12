<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Closure;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Translation\FileLoader;

/**
 * Laravel's translation loader, widened like TranslationLocales: a language set to "pt-PT" reads
 * lang/pt, then lang/pt_PT, then lang/pt-PT, the most specific winning key by key. That covers
 * JSON strings, which Laravel looks up for the exact locale only, so __('Book now') stays English
 * on a pt-PT page even with lang/pt.json there; and PHP files, which trans_choice() checks for the
 * exact locale before picking plural forms, so it showed English plurals with lang/pt/rooms.php.
 * Laravel's own FileLoader is replaced by RegionalFileLoader, a FileLoader still, for code that
 * checks; any other loader is wrapped (widen()).
 *
 * @internal
 */
final readonly class RegionalLoader implements Loader
{
    public function __construct(private Loader $loader) {}

    /** The app's loader, widened to regional locales. */
    public static function widen(Loader $loader): Loader
    {
        // Exactly Laravel's loader: a subclass (a package's database loader) keeps its own behavior.
        return $loader::class === FileLoader::class ? RegionalFileLoader::from($loader) : new self($loader);
    }

    /**
     * The lines of every locale $locale reads, the most specific winning key by key.
     *
     * @param  Closure(string): array<array-key, mixed>  $load
     * @return array<array-key, mixed>
     */
    public static function merge(Closure $load, string $locale): array
    {
        $lines = [];

        foreach (array_reverse(TranslationLocales::expand([$locale])) as $candidate) {
            $lines = array_replace_recursive($lines, $load($candidate));
        }

        return $lines;
    }

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        return self::merge(fn (string $candidate): array => $this->loader->load($candidate, $group, $namespace), $locale);
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
