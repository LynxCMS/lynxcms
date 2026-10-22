<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\RootPage;

/**
 * A page's template strings with their code texts and edits (ADR-0047), for agents, the admin and
 * content packages.
 *
 * @api
 */
final readonly class PageStrings
{
    public function __construct(
        private StringCatalog $catalog,
        private StringStore $store,
        private LanguageRegistry $languages,
        private RootPage $root,
    ) {}

    /**
     * @return list<array{key: string, label: string|null, scope: string, format: string, default: string|array<string, string>, edits: array<string, string>}>
     */
    public function describe(Page $page): array
    {
        $described = [];

        foreach ($this->catalog->forTemplate((string) $page->template()->name) as $definition) {
            $described[] = [
                'key' => $definition->key,
                'label' => $definition->label,
                'scope' => $definition->scope,
                'format' => $definition->format,
                'default' => $definition->default,
                'edits' => $this->edits($page, $definition),
            ];
        }

        return $described;
    }

    /**
     * The page's own edits (not site strings), by language code, for content packages.
     *
     * @return array<string, array<string, string>> language code => key => text
     */
    public function pageEdits(Page $page): array
    {
        $edits = [];

        foreach ($this->catalog->forTemplate((string) $page->template()->name) as $definition) {
            if (! $definition->isSite()) {
                foreach ($this->edits($page, $definition) as $code => $text) {
                    $edits[$code][$definition->key] = $text;
                }
            }
        }

        ksort($edits);

        return $edits;
    }

    /**
     * The edits of one string, from the page (or the home page for site strings).
     *
     * @return array<string, string> language code => edited text
     */
    public function edits(Page $page, StringDefinition $definition): array
    {
        $owner = $definition->isSite() ? $this->root->id() : $page->contentId();
        $edits = [];

        foreach ($owner === null ? [] : ($this->store->forPage($owner)[$definition->key] ?? []) as $languageId => $text) {
            $code = $this->languages->find($languageId)?->code;

            if ($code !== null) {
                $edits[(string) $code] = $text;
            }
        }

        return $edits;
    }
}
