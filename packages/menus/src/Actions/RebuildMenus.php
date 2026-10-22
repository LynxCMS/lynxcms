<?php

declare(strict_types=1);

namespace LynxCms\Menus\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\Menus;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\Reading\MenuBuilds;

/**
 * Drops stored menus (ADR-0068) so the next visit builds them again from their items and the pages
 * they link. Changes made through Lynx already do this; it is for changes Lynx cannot see, such as
 * pages edited with SQL or an imported database.
 */
final class RebuildMenus extends Action
{
    public function __construct(private readonly Menus $menus, private readonly MenuBuilds $builds) {}

    public static function name(): string
    {
        return 'menus.rebuild';
    }

    public static function permission(): string
    {
        return 'menus.manage';
    }

    public static function description(): string
    {
        return 'Rebuild a stored menu, or every menu: the stored copies in every language are dropped and the next visit builds them again. Saving menus and pages already does this; use it after changes made outside Lynx.';
    }

    public static function rules(): array
    {
        return [
            'menu' => ['nullable', 'string', 'max:64', 'regex:'.MenuSchema::NAME_PATTERN],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'menu' => $schema->string()->description('Name of the menu, e.g. "top". Leave out to rebuild every menu.'),
        ];
    }

    /** @return int how many stored builds were (or would be) dropped */
    public function handle(Input $input, ActionContext $context): int
    {
        $name = Value::nullableString($input->get('menu'));
        $menuId = $name === null ? null : $this->menuId($name);
        $count = $this->builds->count($menuId);
        $context->changes->updated('menus', $menuId, ($name === null ? 'Every menu' : "Menu {$name}")." rebuilt: {$count} stored ".($count === 1 ? 'copy' : 'copies').' dropped');

        if (! $context->dryRun) {
            $this->builds->forget($menuId === null ? null : [$menuId]);
        }

        return $count;
    }

    private function menuId(string $name): int
    {
        $page = $this->menus->page($name);

        if (! $page instanceof Page) {
            throw ValidationException::withMessages(['menu' => "There is no menu [{$name}]."]);
        }

        return (int) $page->id;
    }
}
