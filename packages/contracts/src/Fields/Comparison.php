<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Fields;

/**
 * How selectors compare a field's stored "data" column: as text (with %=, ^=, $=), as integers or decimals,
 * as booleans, as dates ("today", "-7 days", "2026-01-01"), or not at all.
 *
 * @api
 */
enum Comparison
{
    case Text;
    case Integer;
    case Decimal;
    case Boolean;
    case Date;
    case None;
}
