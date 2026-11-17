<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Support\Value;

/**
 * Fills a URL name the editor cleared in the Settings tab. The default language's comes from the
 * page's title, and replaces the copies of the old default name in languages without a title of
 * their own, so those keep sharing it; a name typed by hand, or an empty one that follows the
 * default, stays. Another language's comes from its own title, else from the default name.
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
     * @param  string  $previousDefault  the default name before it was cleared: names equal to it are copies
     * @return array<string, string>
     */
    public function fill(array $names, string $code, string $previousDefault = ''): array
    {
        if (! array_key_exists($code, $names) || trim($names[$code]) !== '') {
            return $names;
        }

        if ($code !== $this->defaultCode) {
            $names[$code] = $this->title($code) ?: (PageName::from($names[$this->defaultCode] ?? '') ?: $this->title($this->defaultCode));

            return $names;
        }

        $names[$code] = $this->title($code);

        return $this->shareDefault($names, trim($previousDefault));
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private function shareDefault(array $names, string $previousDefault): array
    {
        foreach (array_keys($names) as $code) {
            $isCopy = $previousDefault !== '' && trim($names[$code]) === $previousDefault;

            if ($code !== $this->defaultCode && $isCopy && $this->title($code) === '') {
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
