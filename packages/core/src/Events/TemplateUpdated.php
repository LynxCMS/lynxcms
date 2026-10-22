<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A template's settings or fields changed (templates.update), so its pages may show or index
 * their values differently.
 *
 * @api
 */
final readonly class TemplateUpdated implements ContentChanged
{
    public function __construct(public int $templateId) {}

    /** Every page may render differently, those on the template and those that show them. */
    public function affectedPages(): ?array
    {
        return null;
    }
}
