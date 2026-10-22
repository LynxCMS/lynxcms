<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use LynxCms\Core\Support\LocalizedText;

/**
 * A tab of the page editor that holds fields: "content", or one a template adds.
 *
 * @api
 */
final readonly class LayoutTab
{
    public const string CONTENT = 'content';

    public function __construct(public string $key, public LocalizedText $label) {}
}
