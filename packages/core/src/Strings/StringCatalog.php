<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use LynxCms\Core\Support\Value;
use LynxCms\Core\View\BlockViews;
use Throwable;

/**
 * The strings each template declares (ADR-0047): its view, the views of the matrix blocks it offers
 * (the ones @matrix renders), and every view those pull in, scanned and cached until one of those
 * files changes, a block view appears, or the template's matrix fields change.
 *
 * @internal
 */
final class StringCatalog
{
    /** Bump when the scanner or the cached shape changes, so cached scans are made again. */
    private const string CACHE_PREFIX = 'lynx-strings:v6:';

    /** @var array<string, array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}> */
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

    /** Whether the template has a view to scan (templates/{name}.blade.php by default). */
    public function hasView(string $template): bool
    {
        return $this->views->exists($this->viewName($template));
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
     * @return array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}
     */
    private function entry(string $template): array
    {
        $view = $this->viewName($template);
        // Resolved here, not kept: BlockViews reads the templates, a service scoped to one request.
        $blocks = app(BlockViews::class)->forTemplate($template);
        // The block types are part of the key, so a scan is made again when the schema changes.
        $key = $this->cacheKey($view).':'.md5(json_encode($blocks, JSON_THROW_ON_ERROR));

        if (isset($this->memo[$key]) && $this->isFresh($this->memo[$key])) {
            return $this->memo[$key];
        }

        $cached = Cache::get($key);

        if (is_array($cached) && $this->isFresh($entry = $this->fromCache($cached))) {
            return $this->memo[$key] = $entry;
        }

        $entry = ['files' => [], 'absent' => [], 'definitions' => [], 'errors' => []];
        $this->collect($view, $entry);
        $this->collectBlocks($blocks, $entry);
        // Plain arrays only: objects come back incomplete under cache.serializable_classes = false.
        Cache::forever($key, [...$entry, 'definitions' => array_map(fn (StringDefinition $definition): array => $definition->toArray(), $entry['definitions'])]);

        return $this->memo[$key] = $entry;
    }

    /**
     * Scans the view each block type renders with: the first of its candidates that exists. The
     * candidates before it are kept as absent, so creating one of them scans again.
     *
     * @param  list<list<string>>  $blocks
     * @param  array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}  $entry
     */
    private function collectBlocks(array $blocks, array &$entry): void
    {
        foreach ($blocks as $candidates) {
            foreach ($candidates as $candidate) {
                if ($this->views->exists($candidate)) {
                    $this->collect($candidate, $entry);

                    continue 2;
                }

                $entry['absent'][] = $candidate;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $cached
     * @return array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}
     */
    private function fromCache(array $cached): array
    {
        return [
            'files' => array_map(Value::int(...), Value::map($cached['files'] ?? [])),
            'absent' => Value::strings($cached['absent'] ?? []),
            'definitions' => array_map(fn (mixed $definition): StringDefinition => StringDefinition::fromArray(Value::array($definition)), Value::list($cached['definitions'] ?? [])),
            'errors' => Value::strings($cached['errors'] ?? []),
        ];
    }

    private function viewName(string $template): string
    {
        return Value::string(config('lynx.views_path', 'templates'), 'templates').'.'.$template;
    }

    /** The folders of site strings decide scopes (ADR-0051), so a scan is kept per setting. */
    private function cacheKey(string $view): string
    {
        return self::CACHE_PREFIX.md5(json_encode(config('lynx.strings.site_folders'), JSON_THROW_ON_ERROR)).':'.$view;
    }

    /**
     * @param  array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}  $entry
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
     * Whether no scanned file changed and no block view that was missing has appeared.
     *
     * @param  array{files: array<string, int>, absent: list<string>, definitions: list<StringDefinition>, errors: list<string>}  $entry
     */
    private function isFresh(array $entry): bool
    {
        foreach ($entry['absent'] as $view) {
            if ($this->views->exists($view)) {
                return false;
            }
        }

        $files = $entry['files'];

        foreach ($files as $path => $mtime) {
            clearstatcache(true, (string) $path);

            if (! is_file((string) $path) || filemtime((string) $path) !== $mtime) {
                return false;
            }
        }

        return $files !== [];
    }
}
