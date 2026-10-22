<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * What MenuReader needs while it walks one menu.
 *
 * @internal
 */
final readonly class ReadContext
{
    /**
     * @param  array<int, list<Page>>  $byParent  menu item pages grouped by parent id, in order
     * @param  array<int, true>  $visiblePages  linked pages visitors may see (published, not hidden)
     */
    public function __construct(
        public array $byParent,
        public array $visiblePages,
        public Language $language,
        public bool $isDefaultLanguage,
    ) {}

    /** Served in the current language: the default language always is (as PageController does). */
    public function isAvailable(Page $page): bool
    {
        return $this->isDefaultLanguage || $page->isActiveIn($this->language);
    }

    /** Items show in every language unless they list the languages they show in. */
    public function isShown(Page $item): bool
    {
        $languages = trim(Value::string($item->get(MenuSchema::LANGUAGES)));

        return $languages === '' || in_array($this->language->code, explode(',', $languages), true);
    }
}
