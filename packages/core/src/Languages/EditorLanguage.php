<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Support\Facades\Lang;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * The language the person at the admin reads labels in (ADR-0140): a locale, which the site need
 * not publish in. Outside the admin there is none, and labels read in the content language. Bound
 * as scoped, so it ends with the request.
 *
 * @internal
 */
final class EditorLanguage
{
    /** The app's translation file for its own labels: lang/{locale}/lynx-schema.php, by the label's text. */
    private const string SITE_LINES = 'lynx-schema';

    private ?string $locale = null;

    /** @var array<string, array<array-key, mixed>> translations of the locale, by group */
    private array $lines = [];

    public function __construct(private readonly LanguageRegistry $languages) {}

    public function use(?string $locale): void
    {
        $this->locale = $locale;
        $this->lines = [];
    }

    /** @param  string|null  $namespace  the translation namespace of the module the text comes from */
    public function read(LocalizedText $text, ?string $namespace = null): string
    {
        if ($this->locale === null || $text->isEmpty()) {
            return $text->in();
        }

        return $this->stored($text) ?? $this->translated($text, $namespace) ?? $text->in();
    }

    /** The text the site stored in the editor's language, the closest variant first. */
    private function stored(LocalizedText $text): ?string
    {
        $texts = $text->toStorage();

        if (! is_array($texts)) {
            return null;
        }

        foreach ($this->matching() as $language) {
            if (isset($texts[$language->code])) {
                return $texts[$language->code];
            }
        }

        return null;
    }

    private function translated(LocalizedText $text, ?string $namespace): ?string
    {
        $key = $text->in($this->languages->hasDefault() ? $this->languages->default() : null);

        foreach ([self::SITE_LINES, ...($namespace === null ? [] : ["{$namespace}::schema"])] as $group) {
            $line = Value::nullableString($this->group($group)[$key] ?? null);

            if ($line !== null && $line !== '') {
                return $line;
            }
        }

        return null;
    }

    /**
     * The site's languages the editor reads, the closest first: the locale itself, then its bare
     * language, then other variants of it.
     *
     * @return list<Language>
     */
    private function matching(): array
    {
        $wanted = $this->normal((string) $this->locale);
        $bare = $this->bare($wanted);
        $ranked = [];

        foreach ($this->languages->all() as $language) {
            $names = [$this->normal((string) $language->code), $this->normal($language->appLocale())];
            $rank = match (true) {
                in_array($wanted, $names, true) => 0,
                in_array($bare, $names, true) => 1,
                in_array($bare, array_map($this->bare(...), $names), true) => 2,
                default => null,
            };

            if ($rank !== null) {
                $ranked[$rank][] = $language;
            }
        }

        ksort($ranked);

        return array_merge(...array_values($ranked));
    }

    /**
     * The locale's own lines, without the app's fallback: an English editor would read labels in it,
     * as English labels need no file.
     *
     * @return array<array-key, mixed>
     */
    private function group(string $group): array
    {
        return $this->lines[$group] ??= Value::array(Lang::get($group, [], $this->locale, false));
    }

    private function normal(string $locale): string
    {
        return strtolower(strtr($locale, '_', '-'));
    }

    private function bare(string $locale): string
    {
        return explode('-', $locale)[0];
    }
}
