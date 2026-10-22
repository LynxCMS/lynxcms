<?php

declare(strict_types=1);

namespace LynxCms\Menus\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\LinkRel;
use LynxCms\Core\Pages\Page;
use LynxCms\Menus\Menus;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\Support\ItemValues;
use UnexpectedValueException;

/**
 * Creates or updates a menu item: a section label, a page (optionally with its child pages)
 * or a custom link. Sorting, moving and deleting items use pages.sort, pages.move and pages.delete.
 */
final class SaveMenuItem extends Action
{
    public function __construct(private readonly Menus $menus, private readonly ItemValues $values) {}

    public static function name(): string
    {
        return 'menus.save_item';
    }

    public static function permission(): string
    {
        return 'menus.manage';
    }

    public static function description(): string
    {
        return 'Add or update a menu item. kind "section" is a label without a link, "page" links a page '
            .'(label optional, "children": true adds its child pages), "link" is a custom URL with an optional target.';
    }

    public static function rules(): array
    {
        return [
            'item' => ['sometimes', 'nullable', 'integer'],
            'menu' => ['required_without:item', 'nullable', 'string'],
            'parent' => ['sometimes', 'nullable', 'integer'],
            'kind' => ['required', Rule::in(ItemValues::KINDS)],
            'label' => ['sometimes', 'nullable'],
            'page' => ['sometimes', 'nullable'],
            'url' => ['sometimes', 'nullable'],
            'target' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rel' => ['sometimes', 'nullable', 'array'],
            'rel.*' => ['string', Rule::in(LinkRel::CHOICES)],
            'children' => ['sometimes', 'boolean'],
            'depth' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.MenuSchema::MAX_DEPTH],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.MenuSchema::MAX_LIMIT],
            'class' => ['sometimes', 'nullable', 'string', 'max:200', 'not_regex:/["\'<>]/'],
            'icon_before' => ['sometimes', 'nullable', 'string', 'max:200', 'not_regex:/["\'<>]/'],
            'icon_after' => ['sometimes', 'nullable', 'string', 'max:200', 'not_regex:/["\'<>]/'],
            'languages' => ['sometimes', 'array', 'min:1'],
            'languages.*' => ['string'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'item' => $schema->integer()->description('Id of an item to update. Omit to add one.'),
            'menu' => $schema->string()->description('Menu name, e.g. "top". Required when adding.'),
            'parent' => $schema->integer()->description('Id of the item to nest under. Default: the top level of the menu.'),
            'kind' => $schema->string()->enum(ItemValues::KINDS)->description('section, page or link.')->required(),
            'label' => $schema->object()->description('Label, a string or {"<language code>": "label"}. Optional for pages.'),
            'page' => $schema->string()->description('For kind "page": id, default-language path or page name of the page.'),
            'url' => $schema->object()->description('For kind "link": URL, a string or per language. Relative, #fragment, http(s), mailto or tel.'),
            'target' => $schema->string()->description('_blank, _self, _parent, _top or a frame name.'),
            'rel' => $schema->array()->items($schema->string()->enum(LinkRel::CHOICES))->description('Relations of the link, e.g. ["nofollow"]. Links opening a new window also get noopener.'),
            'children' => $schema->boolean()->description('For kind "page": also show its child pages.'),
            'depth' => $schema->integer()->description('With "children": levels of child pages (1 to '.MenuSchema::MAX_DEPTH.', default '.MenuSchema::DEFAULT_DEPTH.').'),
            'limit' => $schema->integer()->description('With "children": most child pages per level (1 to '.MenuSchema::MAX_LIMIT.', default '.MenuSchema::DEFAULT_LIMIT.').'),
            'class' => $schema->string()->description('CSS classes for this item.'),
            'icon_before' => $schema->string()->description('Icon shown before the label, e.g. "fa fa-home" or an icon name your views understand.'),
            'icon_after' => $schema->string()->description('Icon shown after the label.'),
            'languages' => $schema->array()->items($schema->string())->description('Language codes the item shows in. Default: all.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        // Menus installed by an earlier version get the fields added since.
        $context->run(InstallMenus::class);
        $item = $this->existingItem($input);
        $menu = $item instanceof Page ? $this->menuOfItem($item, $input) : $this->menu($input);
        $values = $this->values->build($input, $item);
        $this->authorizeLinkedPage($input, $context);

        $page = $context->run(SavePage::class, $item instanceof Page
            ? ['id' => $item->id, 'values' => $values]
            : [
                'template' => MenuSchema::ITEM_TEMPLATE,
                'parent' => $this->parent($input, $menu),
                'names' => [lynx()->languages->default()->code => 'item-'.Str::lower(Str::random(10))],
                'values' => $values,
            ]);

        return $page instanceof Page ? $page : throw new UnexpectedValueException('pages.save did not return a page.');
    }

    /** Linking a page means finding it first: the user must be able to view it (ADR-0033). */
    private function authorizeLinkedPage(Input $input, ActionContext $context): void
    {
        $reference = $input->get('page');
        $page = is_int($reference) || is_string($reference) ? lynx()->pages->get($reference) : null;

        if ($page instanceof Page) {
            $context->authorize('pages.view', $page);
        }
    }

    private function existingItem(Input $input): ?Page
    {
        if ($input->int('item') === null) {
            return null;
        }

        $item = lynx()->pages->get($input->int('item'));

        return $item instanceof Page && $item->template()->name === MenuSchema::ITEM_TEMPLATE
            ? $item
            : throw ValidationException::withMessages(['item' => 'Menu item ['.$input->int('item').'] does not exist.']);
    }

    /** The item's own menu; when "menu" is given too it must match, so an item id cannot reach into another menu. */
    private function menuOfItem(Page $item, Input $input): Page
    {
        $menu = $this->menus->menuOf($item);
        $expected = $input->string('menu');

        return $expected === null || $expected === $menu->name(lynx()->languages->default())
            ? $menu
            : throw ValidationException::withMessages(['item' => "Menu item [{$item->id}] is not in menu [{$expected}]."]);
    }

    private function menu(Input $input): Page
    {
        return $this->menus->page((string) $input->string('menu'))
            ?? throw ValidationException::withMessages(['menu' => 'Menu ['.$input->string('menu').'] does not exist.']);
    }

    /** The menu itself or one of its items. */
    private function parent(Input $input, Page $menu): int
    {
        $parent = $input->int('parent') ?? (int) $menu->id;
        $isInMenu = $parent === $menu->id
            || lynx()->pages->count("id={$parent}, has_parent={$menu->id}, template=".MenuSchema::ITEM_TEMPLATE.', include=all') === 1;

        return $isInMenu ? $parent : throw ValidationException::withMessages(['parent' => 'The parent must be this menu or one of its items.']);
    }
}
