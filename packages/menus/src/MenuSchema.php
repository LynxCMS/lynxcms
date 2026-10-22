<?php

declare(strict_types=1);

namespace LynxCms\Menus;

/**
 * Names of the templates and fields the menu module installs. Menus are pages (ADR-0008):
 * a hidden system page holds one "menu" page per menu, whose descendants are "menu-item" pages.
 *
 * @api
 */
final class MenuSchema
{
    public const string ROOT_TEMPLATE = 'menus';

    public const string MENU_TEMPLATE = 'menu';

    public const string ITEM_TEMPLATE = 'menu-item';

    public const string ROOT_NAME = '_menus';

    public const string PAGE = 'menu_page';

    public const string URL = 'menu_url';

    public const string TARGET = 'menu_target';

    /** Relations chosen for the link, e.g. "nofollow noreferrer" (LinkRel::CHOICES). */
    public const string REL = 'menu_rel';

    public const string CHILDREN = 'menu_children';

    public const string CSS_CLASS = 'menu_class';

    public const string ICON_BEFORE = 'menu_icon_before';

    public const string ICON_AFTER = 'menu_icon_after';

    /** Comma-separated codes of the languages an item shows in; empty means every language. */
    public const string LANGUAGES = 'menu_languages';

    public const string DEPTH = 'menu_depth';

    public const string LIMIT = 'menu_limit';

    /** Levels of child pages a page item adds when it sets none. */
    public const int DEFAULT_DEPTH = 1;

    public const int MAX_DEPTH = 10;

    /** Child pages per level a page item adds when it sets no limit. */
    public const int DEFAULT_LIMIT = 10;

    public const int MAX_LIMIT = 100;

    /** Menu names are short slugs such as "top" or "footer-legal". */
    public const string NAME_PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    /**
     * Fields to create: name => [type, translatable, label, settings].
     *
     * @return array<string, array{0: string, 1: bool, 2: string, 3: array<string, mixed>}>
     */
    public static function fields(): array
    {
        return [
            self::PAGE => ['page', false, 'Menu page', ['multiple' => false]],
            self::URL => ['text', true, 'Menu link URL', []],
            self::TARGET => ['text', false, 'Menu link target', []],
            self::REL => ['text', false, 'Menu link relation', []],
            self::CHILDREN => ['toggle', false, 'Include child pages', []],
            self::CSS_CLASS => ['text', false, 'Menu CSS class', []],
            self::ICON_BEFORE => ['text', false, 'Menu icon before the label', []],
            self::ICON_AFTER => ['text', false, 'Menu icon after the label', []],
            self::LANGUAGES => ['text', false, 'Menu item languages', []],
            self::DEPTH => ['integer', false, 'Menu depth', []],
            self::LIMIT => ['integer', false, 'Menu child page limit', []],
        ];
    }

    /**
     * Templates to create: name => [label, fields, singleton].
     *
     * @return array<string, array{0: string, 1: list<string|array<string, mixed>>, 2: bool}>
     */
    public static function templates(): array
    {
        return [
            self::ROOT_TEMPLATE => ['Menus', ['title'], true],
            self::MENU_TEMPLATE => ['Menu', [['name' => 'title', 'required' => true], self::CSS_CLASS], false],
            self::ITEM_TEMPLATE => ['Menu item', ['title', self::PAGE, self::URL, self::TARGET, self::REL, self::CHILDREN, self::DEPTH, self::LIMIT, self::CSS_CLASS, self::ICON_BEFORE, self::ICON_AFTER, self::LANGUAGES], false],
        ];
    }
}
