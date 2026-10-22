<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Visibility;

/**
 * A visibility rule that can tell whether the current visitor sees what a visitor with no cookies
 * and no account sees (ADR-0068). Work done for that default visitor, such as a stored menu, is
 * then shared with them. Rules that do not implement it count as treating every visitor apart.
 *
 * @api
 */
interface VariesByVisitor
{
    /** Null for the default visitor; otherwise a key that differs between what visitors may see. */
    public function audience(): ?string;
}
