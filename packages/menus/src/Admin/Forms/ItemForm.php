<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin\Forms;

use Livewire\Form;
use LynxCms\Core\Fields\LinkRel;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * The menu item form: kind, label and URL per language, page, target and classes.
 * Validation happens in menus.save_item, so the rules stay the same for MCP and the agent.
 *
 * @internal
 */
final class ItemForm extends Form
{
    /** Target choices the form offers besides a custom frame name. */
    public const array TARGETS = ['' => 'Same window', '_blank' => 'New tab', 'custom' => 'Custom…'];

    public ?int $id = null;

    public ?int $parent = null;

    public string $kind = 'section';

    /** @var array<string, string> */
    public array $label = [];

    public ?int $page = null;

    public string $pageTitle = '';

    /** @var array<string, string> */
    public array $url = [];

    public string $targetChoice = '';

    public string $target = '';

    /** @var list<string> chosen relations, e.g. ["nofollow"] */
    public array $rel = [];

    public string $class = '';

    public string $iconBefore = '';

    public string $iconAfter = '';

    public bool $children = false;

    public ?int $depth = null;

    public ?int $limit = null;

    /** @var list<string> codes of the languages the item shows in */
    public array $languages = [];

    public function startNew(int $parent): void
    {
        $this->reset();
        $this->parent = $parent;
        $this->languages = $this->allLanguages();
    }

    public function fillFrom(Page $item): void
    {
        $this->reset();
        $this->id = (int) $item->id;
        $linked = $item->get(MenuSchema::PAGE);
        $page = $linked instanceof Page ? $linked : null;

        foreach (lynx()->languages->all() as $language) {
            $this->label[$language->code] = Value::string($item->getLanguageValue($language, 'title'));
            $this->url[$language->code] = Value::string($item->getLanguageValue($language, MenuSchema::URL));
        }

        $this->page = $page instanceof Page ? (int) $page->id : null;
        $this->pageTitle = $page instanceof Page ? Value::string($page->get('title')) : '';
        $this->kind = $page instanceof Page ? 'page' : (array_filter($this->url) !== [] ? 'link' : 'section');
        $this->children = $item->get(MenuSchema::CHILDREN) === true;
        $depth = $item->get(MenuSchema::DEPTH);
        $limit = $item->get(MenuSchema::LIMIT);
        $this->depth = is_int($depth) ? $depth : null;
        $this->limit = is_int($limit) ? $limit : null;
        $this->class = Value::string($item->get(MenuSchema::CSS_CLASS));
        $this->iconBefore = Value::string($item->get(MenuSchema::ICON_BEFORE));
        $this->iconAfter = Value::string($item->get(MenuSchema::ICON_AFTER));
        $shown = trim(Value::string($item->get(MenuSchema::LANGUAGES)));
        $this->languages = $shown === '' ? $this->allLanguages() : explode(',', $shown);
        $this->fillTarget(Value::string($item->get(MenuSchema::TARGET)));
        $this->rel = $item->templateField(MenuSchema::REL) === null ? [] : LinkRel::chosen(Value::string($item->get(MenuSchema::REL)));
    }

    /**
     * The relations editors may tick, from the admin's link settings (as the rich text link dialog).
     *
     * @return array<string, string> relation => label
     */
    public static function relChoices(): array
    {
        return array_intersect_key(array_map(Value::string(...), Value::map(config('lynx-admin.links.rels', []))), array_flip(LinkRel::CHOICES));
    }

    public function choosePage(Page $page): void
    {
        $this->page = (int) $page->id;
        $this->pageTitle = Value::string($page->get('title')) ?: $page->name();
    }

    /**
     * Input for menus.save_item.
     *
     * @return array<string, mixed>
     */
    public function toInput(string $menu): array
    {
        // The menu is always sent, so menus.save_item refuses an item id from another menu.
        $where = $this->id !== null ? ['item' => $this->id, 'menu' => $menu] : ['menu' => $menu, 'parent' => $this->parent];

        return $where + [
            'kind' => $this->kind,
            'label' => $this->label,
            'page' => $this->page,
            'url' => $this->url,
            'target' => $this->targetChoice === 'custom' ? $this->target : $this->targetChoice,
            'rel' => array_values($this->rel),
            'children' => $this->children,
            'depth' => $this->depth,
            'limit' => $this->limit,
            'class' => $this->class,
            'icon_before' => $this->iconBefore,
            'icon_after' => $this->iconAfter,
            'languages' => array_values($this->languages),
        ];
    }

    /**
     * @return list<string>
     */
    private function allLanguages(): array
    {
        return array_values(lynx()->languages->all()->pluck('code')->map(Value::string(...))->all());
    }

    private function fillTarget(string $target): void
    {
        $isPreset = array_key_exists($target, self::TARGETS) && $target !== 'custom';
        $this->targetChoice = $isPreset ? $target : 'custom';
        $this->target = $isPreset ? '' : $target;
    }
}
