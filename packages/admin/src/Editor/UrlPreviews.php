<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Pages\PageUrl;
use LynxCms\Core\Support\Value;

/**
 * The URLs the Settings tab previews for a page's names, with the rule saving uses (PathBuilder,
 * ADR-0011): the language's prefix, then each parent's name in that language, or its default name
 * when the parent has no version in it, then the page's own name. Also which parent lends its
 * default name in each language, so the tab can say why that URL looks like the default one.
 *
 * @internal
 */
final readonly class UrlPreviews
{
    /** @var list<Page> the parents below the home page, top first: the home page adds no segment */
    private array $parents;

    public function __construct(private Page $page)
    {
        $parents = [];

        for ($parent = $page->parent(); $parent instanceof Page && $parent->parentId !== null; $parent = $parent->parent()) {
            array_unshift($parents, $parent);
        }

        $this->parents = $parents;
    }

    /**
     * @param  array<string, string>  $names  the names as typed, per language code
     * @return array<string, string|null> per language code; null when the page has no URL
     */
    public function urls(array $names): array
    {
        $default = lynx()->languages->default();
        $defaultName = PageName::from($names[Value::string($default->code)] ?? '');
        $urls = [];

        foreach (lynx()->languages->all() as $language) {
            $code = Value::string($language->code);
            $name = PageName::from($names[$code] ?? '') ?: $defaultName;
            $urls[$code] = $this->page->parent() instanceof Page && $this->page->template()->hasUrl() && $name !== ''
                ? PageUrl::of(Value::string($language->url_prefix), implode('/', [...$this->segments($language, $default), $name]))
                : null;
        }

        return $urls;
    }

    /** @return array<string, string> language code => the title of the nearest parent with no version in it */
    public function borrowedFrom(): array
    {
        $borrowed = [];

        foreach (lynx()->languages->all() as $language) {
            foreach (array_reverse($this->parents) as $parent) {
                if (! isset($parent->rawNames()[$language->id])) {
                    $borrowed[Value::string($language->code)] = Value::string($parent->title);

                    break;
                }
            }
        }

        return $borrowed;
    }

    /** @return list<string> each parent's name in the language, else its default name */
    private function segments(Language $language, Language $default): array
    {
        return array_map(function (Page $parent) use ($language, $default): string {
            $row = $parent->rawNames()[$language->id] ?? $parent->rawNames()[$default->id] ?? null;

            return Value::string($row['name'] ?? $parent->id);
        }, $this->parents);
    }
}
