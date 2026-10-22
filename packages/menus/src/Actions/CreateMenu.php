<?php

declare(strict_types=1);

namespace LynxCms\Menus\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Menus\Menus;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\Support\Translations;
use UnexpectedValueException;

/**
 * Creates a menu, installing the menu builder first when needed.
 */
final class CreateMenu extends Action
{
    public function __construct(private readonly Menus $menus) {}

    public static function name(): string
    {
        return 'menus.create';
    }

    public static function permission(): string
    {
        return 'menus.manage';
    }

    public static function description(): string
    {
        return 'Create a menu such as "top" or "footer". Its title may be given per language. Items are added with menus.save_item.';
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:'.MenuSchema::NAME_PATTERN],
            'title' => ['required'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Short slug used in templates, e.g. "top" or "footer-legal".')->required(),
            'title' => $schema->object()->description('Title for editors, a string or {"<language code>": "title"}.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Page
    {
        $context->run(InstallMenus::class);
        $name = (string) $input->string('name');

        if ($this->menus->page($name) instanceof Page) {
            throw ValidationException::withMessages(['name' => "A menu named [{$name}] already exists."]);
        }

        $title = Translations::normalize($input->get('title'), 'title');

        if (! Translations::hasDefault($title)) {
            throw ValidationException::withMessages(['title' => 'A title in the default language is required.']);
        }

        $page = $context->run(SavePage::class, [
            'template' => MenuSchema::MENU_TEMPLATE,
            'parent' => (int) $this->menus->root()?->id,
            'names' => [lynx()->languages->default()->code => $name],
            'values' => ['title' => $title],
        ]);

        return $page instanceof Page ? $page : throw new UnexpectedValueException('pages.save did not return a page.');
    }
}
