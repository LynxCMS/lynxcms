<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use LynxCms\Core\Support\Value;

/**
 * The site's languages as the menu screens' translated inputs list them: code, name and tab label,
 * the default language first.
 *
 * @internal
 */
final class MenuLanguages
{
    /** @return list<array{code: string, name: string, label: string}> */
    public static function all(): array
    {
        return array_values(lynx()->languages->all()->map(fn ($language): array => ['code' => Value::string($language->code), 'name' => Value::string($language->name), 'label' => $language->tabLabel()])->all());
    }
}
