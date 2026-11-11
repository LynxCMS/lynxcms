<?php

declare(strict_types=1);

namespace LynxCms\Finder\Catalog;

/**
 * What a finder condition looks at, which decides its operators and the input its value takes.
 * Moment is a page's own created, modified or published time; Date is a date field; Items is a
 * field of items (images, files, repeaters, matrices), found by how many it holds.
 *
 * @api
 */
enum Kind: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case Moment = 'moment';
    case Pages = 'pages';
    case Items = 'items';
    case Template = 'template';
    case Status = 'status';
    case Parent = 'parent';

    /**
     * The operators a condition of this kind offers, the first being the default.
     *
     * @return list<Operator>
     */
    public function operators(): array
    {
        $presence = [Operator::Blank, Operator::Filled];

        return match ($this) {
            self::Text => [Operator::Contains, Operator::Is, Operator::Not, Operator::StartsWith, Operator::EndsWith, Operator::Words, ...$presence],
            self::Number => [Operator::Is, Operator::Not, Operator::Less, Operator::LessOrEqual, Operator::Greater, Operator::GreaterOrEqual, Operator::Between, ...$presence],
            self::Boolean => [Operator::On, Operator::Off],
            self::Date => [Operator::Day, Operator::Before, Operator::After, Operator::Between, Operator::LastDays, ...$presence],
            self::Moment => [Operator::LastDays, Operator::Day, Operator::Before, Operator::After, Operator::Between],
            self::Pages => [Operator::Is, Operator::Not, Operator::AtLeast, ...$presence],
            self::Items => [Operator::Filled, Operator::Blank, Operator::AtLeast],
            self::Template, self::Status, self::Parent => [Operator::Is, Operator::Not],
        };
    }

    /** The input the value takes with this operator: none, text, number, date, days, choice or pages. */
    public function input(Operator $operator): string
    {
        return match (true) {
            ! $operator->takesValue() => 'none',
            $operator === Operator::LastDays, $operator === Operator::AtLeast => 'number',
            $this === self::Date, $this === self::Moment => 'date',
            $this === self::Number => 'number',
            $this === self::Pages, $this === self::Parent => 'pages',
            $this === self::Template, $this === self::Status => 'choice',
            default => 'text',
        };
    }
}
