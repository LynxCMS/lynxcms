<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

/**
 * How a template's field shows in the page editor (ADR-0063), its "editor" setting. Locked and
 * hidden fields are written only by code, imports, actions, agents and MCP.
 *
 * @api
 */
enum FieldDisplay: string
{
    case Open = 'open';
    case Collapsed = 'collapsed';
    case CollapsedBlank = 'collapsed_blank';
    case CollapsedPopulated = 'collapsed_populated';
    case Locked = 'locked';
    case Hidden = 'hidden';

    /** Whether editors change the value in the page editor. */
    public function isEditable(): bool
    {
        return ! in_array($this, [self::Locked, self::Hidden], true);
    }

    /** Whether the field starts closed, given whether it has a value. */
    public function startsCollapsed(bool $hasValue): bool
    {
        return match ($this) {
            self::Collapsed => true,
            self::CollapsedBlank => ! $hasValue,
            self::CollapsedPopulated => $hasValue,
            default => false,
        };
    }

    public function isCollapsible(): bool
    {
        return in_array($this, [self::Collapsed, self::CollapsedBlank, self::CollapsedPopulated], true);
    }
}
