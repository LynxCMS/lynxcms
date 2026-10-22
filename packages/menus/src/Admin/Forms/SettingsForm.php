<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin\Forms;

use Livewire\Form;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * A menu's own options: title per language and CSS class. Child page depth and limit are per item.
 *
 * @internal
 */
final class SettingsForm extends Form
{
    /** @var array<string, string> */
    public array $title = [];

    public string $class = '';

    public function fillFrom(Page $menu): void
    {
        foreach (lynx()->languages->all() as $language) {
            $this->title[$language->code] = Value::string($menu->getLanguageValue($language, 'title'));
        }

        $this->class = Value::string($menu->get(MenuSchema::CSS_CLASS));
    }

    /** Saves through pages.save, like any other page. */
    public function save(Page $menu): void
    {
        foreach ($this->title as $code => $title) {
            $menu->setLanguageValue($code, 'title', trim($title) === '' ? null : trim($title));
        }

        $menu->setLanguageValue(lynx()->languages->default(), MenuSchema::CSS_CLASS, trim($this->class) === '' ? null : trim($this->class));
        lynx()->pages->save($menu);
    }
}
