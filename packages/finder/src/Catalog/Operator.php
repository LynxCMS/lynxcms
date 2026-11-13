<?php

declare(strict_types=1);

namespace LynxCms\Finder\Catalog;

/**
 * How a finder condition compares. The values are the keys searches store, so they never change.
 *
 * @api
 */
enum Operator: string
{
    case Is = 'is';
    case Not = 'not';
    case Contains = 'contains';
    case StartsWith = 'starts';
    case EndsWith = 'ends';
    case Words = 'words';
    case Less = 'lt';
    case LessOrEqual = 'lte';
    case Greater = 'gt';
    case GreaterOrEqual = 'gte';
    case Between = 'between';
    case On = 'on';
    case Off = 'off';
    case Day = 'day';
    case Before = 'before';
    case After = 'after';
    case LastDays = 'last_days';
    case AtLeast = 'at_least';
    case Blank = 'blank';
    case Filled = 'filled';

    /** Whether the condition needs a value (or pages) besides its subject. */
    public function takesValue(): bool
    {
        return ! in_array($this, [self::Blank, self::Filled, self::On, self::Off], true);
    }

    /** Whether a page with no value matches: "is not", "is blank" and "is off". */
    public function matchesMissing(): bool
    {
        return in_array($this, [self::Not, self::Blank, self::Off], true);
    }

    /** The selector operator for a plain comparison, or null for those written another way. */
    public function selectorOperator(): ?string
    {
        return match ($this) {
            self::Is => '=',
            self::Not => '!=',
            self::Contains => '%=',
            self::StartsWith => '^=',
            self::EndsWith => '$=',
            self::Words => '~=',
            self::Less, self::Before => '<',
            self::LessOrEqual => '<=',
            self::Greater => '>',
            self::GreaterOrEqual, self::AtLeast => '>=',
            default => null,
        };
    }
}
