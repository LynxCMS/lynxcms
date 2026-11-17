<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Support\Value;

/**
 * Fills a URL name the editor cleared in the Settings tab. The default language's comes from the
 * page's title and also goes to every language without a title of its own, so those keep sharing
 * the default name. Another language's comes from its own title, else from the default name.
 *
 * @internal
 */
final readonly class NameSuggestions
{
    private string $defaultCode;

    public function __construct(private Page $page)
    {
        $this->defaultCode = Value::string(lynx()->languages->default()->code);
    }

    /**
     * The names with $code filled when it is empty; names that are not empty stay as typed.
     *
     * @param  array<string, string>  $names  per language code
     * @return array<string, string>
     */
    public function fill(array $names, string $code): array
    {
        if (! array_key_exists($code, $names) || trim($names[$code]) !== '') {
            return $names;
        }

        if ($code !== $this->defaultCode) {
            $names[$code] = $this->title($code) ?: (PageName::from($names[$this->defaultCode] ?? '') ?: $this->title($this->defaultCode));

            return $names;
        }

        $names[$code] = $this->title($code);

        return $this->shareDefault($names);
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private function shareDefault(array $names): array
    {
        foreach (array_keys($names) as $code) {
            if ($code !== $this->defaultCode && $this->title($code) === '') {
                $names[$code] = $names[$this->defaultCode];
            }
        }

        return $names;
    }

    private function title(string $code): string
    {
        return PageName::from(Value::string($this->page->getLanguageValue($code, 'title')));
    }
}
