<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * The strings each template declares (ADR-0047): its view and every view it pulls in, scanned and
 * cached until one of those files changes.
 *
 * @internal
 */
final class StringCatalog
{
    /** Bump when the scanner or the cached shape changes, so cached scans are made again. */
    private const string CACHE_PREFIX = 'lynx-strings:v4:';

    /** @var array<string, array{files: array<string, int>, definitions: list<StringDefinition>, errors: list<string>}> */
    private array $memo = [];

    public function __construct(private readonly StringScanner $scanner, private readonly Factory $views) {}

    /**
     * @return array<string, StringDefinition> key => definition, the first declaration of a key winning
     */
    public function forTemplate(string $template): array
    {
        $definitions = [];

        foreach ($this->entry($template)['definitions'] as $definition) {
            $definitions[$definition->key] ??= $definition;
        }

        return $definitions;
    }

    /**
     * Site strings declared by any template, which every page shares (stored on the home page).
     *
     * @return array<string, StringDefinition>
     */
    public function siteStrings(): array
    {
        $strings = [];

        foreach (lynx()->templates->all() as $template) {
            foreach ($this->forTemplate((string) $template->name) as $key => $definition) {
                if ($definition->isSite()) {
                    $strings[$key] ??= $definition;
                }
            }
        }

        ksort($strings);

        return $strings;
    }

    /** @return list<string> */
    public function errors(string $template): array
    {
        return $this->entry($template)['errors'];
    }

    /**
     * Keys declared more than once across templates with different code texts.
     *
     * @param  list<string>  $templates
     * @return array<string, list<string>> key => views declaring it
     */
    public function conflicts(array $templates): array
    {
        $seen = [];
        $conflicts = [];

        foreach ($templates as $template) {
            foreach ($this->entry($template)['definitions'] as $definition) {
                $first = $seen[$definition->key] ?? null;

                if ($first === null) {
                    $seen[$definition->key] = $definition;
                } elseif ($first->default !== $definition->default) {
                    $conflicts[$definition->key] = array_values(array_unique([...($conflicts[$definition->key] ?? [$first->view]), $definition->view]));
                }
            }
        }

        return $conflicts;
    }

    /**
     * Code texts given for language codes no language has, such as "en" on a site whose English
     * is "default". Such a text is never picked by language: it only shows as the last resort.
     *
     * @param  list<string>  $templates
     * @return array<string, list<string>> key => unknown codes
     */
    public function unknownLanguages(array $templates): array
    {
        $codes = Value::strings(lynx()->languages->all()->pluck('code')->all());
        $unknown = [];

        foreach ($templates as $template) {
            foreach ($this->entry($template)['definitions'] as $definition) {
                $missing = is_array($definition->default) ? array_values(array_diff(array_map(strval(...), array_keys($definition->default)), $codes)) : [];

                if ($missing !== []) {
                    $unknown[$definition->key] = $missing;
                }
            }
        }

        return $unknown;
    }

    /**
     * @return array{files: array<string, int>, definitions: list<StringDefinition>, errors: list<string>}
     */
    private function entry(string $template): array
    {
        $view = Value::string(config('lynx.views_path', 'templates'), 'templates').'.'.$template;
        if (isset($this->memo[$view]) && $this->isFresh($this->memo[$view]['files'])) {
            return $this->memo[$view];
        }

        $cached = Cache::get($this->cacheKey($view));

        if (is_array($cached) && $this->isFresh(Value::array($cached['files'] ?? []))) {
            return $this->memo[$view] = $this->fromCache($cached);
        }

        $entry = ['files' => [], 'definitions' => [], 'errors' => []];
        $this->collect($view, $entry);
        // Plain arrays only: objects come back incomplete under cache.serializable_classes = false.
        Cache::forever($this->cacheKey($view), [...$entry, 'definitions' => array_map(fn (StringDefinition $definition): array => $definition->toArray(), $entry['definitions'])]);

        return $this->memo[$view] = $entry;
    }

    /**
     * @param  array<array-key, mixed>  $cached
     * @return array{files: array<string, int>, definitions: list<StringDefinition>, errors: list<string>}
     */
    private function fromCache(array $cached): array
    {
        return [
            'files' => array_map(Value::int(...), Value::map($cached['files'] ?? [])),
            'definitions' => array_map(fn (mixed $definition): StringDefinition => StringDefinition::fromArray(Value::array($definition)), Value::list($cached['definitions'] ?? [])),
            'errors' => Value::strings($cached['errors'] ?? []),
        ];
    }

    /** The folders of site strings decide scopes (ADR-0051), so a scan is kept per setting. */
    private function cacheKey(string $view): string
    {
        return self::CACHE_PREFIX.md5(json_encode(config('lynx.strings.site_folders'), JSON_THROW_ON_ERROR)).':'.$view;
    }

    /**
     * @param  array{files: array<string, int>, definitions: list<StringDefinition>, errors: list<string>}  $entry
     */
    private function collect(string $view, array &$entry): void
    {
        try {
            $path = $this->views->getFinder()->find($view);
        } catch (Throwable) {
            return;
        }

        if (isset($entry['files'][$path])) {
            return;
        }

        $entry['files'][$path] = (int) filemtime($path);
        $report = $this->scanner->scan($path);
        array_push($entry['definitions'], ...$report->definitions);
        array_push($entry['errors'], ...$report->errors);

        foreach ($report->views as $included) {
            $this->collect($included, $entry);
        }
    }

    /**
     * @param  array<array-key, mixed>  $files  path => modification time when scanned
     */
    private function isFresh(array $files): bool
    {
        foreach ($files as $path => $mtime) {
            clearstatcache(true, (string) $path);

            if (! is_file((string) $path) || filemtime((string) $path) !== $mtime) {
                return false;
            }
        }

        return $files !== [];
    }
}
