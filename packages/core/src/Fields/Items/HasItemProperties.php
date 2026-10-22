<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

/**
 * An item field type whose items hold fixed values selectors can test, such as the images types
 * (ADR-0049): "gallery.tags=hero", "gallery.width>=1200", and inside repeater and matrix items
 * "blocks.gallery.tags=hero". Shared properties live in the item row's data, translatable ones in
 * the language rows (or, nested in an item, as {"language code": text}).
 *
 * @api
 */
interface HasItemProperties
{
    /**
     * @return array<string, ItemProperty> property name => how to compare it
     */
    public function itemProperties(): array;
}
