<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use LynxCms\Core\Support\LocalizedText;

/**
 * A titled group of fields in the page editor. Its fields share its tab and column.
 *
 * @api
 */
final readonly class LayoutSection
{
    public function __construct(
        public string $key,
        public LocalizedText $label,
        public string $tab = LayoutTab::CONTENT,
        public string $column = TemplateLayout::MAIN,
        public bool $collapsed = false,
    ) {}
}
