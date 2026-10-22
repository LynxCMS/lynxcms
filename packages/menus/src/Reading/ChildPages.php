<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * How many child pages a page item adds: levels and pages per level, with defaults.
 *
 * @internal
 */
final readonly class ChildPages
{
    public function __construct(public int $depth, public int $limit) {}

    public static function of(Page $item): self
    {
        return new self(
            max(1, Value::int($item->get(MenuSchema::DEPTH), MenuSchema::DEFAULT_DEPTH)),
            max(1, Value::int($item->get(MenuSchema::LIMIT), MenuSchema::DEFAULT_LIMIT)),
        );
    }
}
