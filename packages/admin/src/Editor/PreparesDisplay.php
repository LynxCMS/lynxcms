<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * A converter that also prepares stored values for the read-only display (change review,
 * fallback previews), loading what the display view needs in one go so the view runs no queries.
 *
 * @api
 */
interface PreparesDisplay
{
    public function display(mixed $value): mixed;
}
