<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use Carbon\CarbonImmutable;
use LynxCms\Finder\Catalog\Kind;
use LynxCms\Finder\Catalog\Operator;
use LynxCms\Finder\Catalog\Subject;
use LynxCms\Finder\Search\Condition;

/**
 * Writes one finder condition as selector clauses, after checking its value: numbers and dates
 * must be numbers and dates, choices must be among the subject's options, and text loses its
 * double quotes and goes inside quotes, so it can never add terms of its own. An empty list means
 * the condition is incomplete or its value is refused.
 *
 * @internal
 */
final readonly class ConditionWriter
{
    private const string DATE = 'Y-m-d';

    /**
     * @return list<string>
     */
    public function clauses(Subject $subject, Operator $operator, Condition $condition): array
    {
        $name = $subject->name;

        return match (true) {
            ! $operator->takesValue() => [$name.$this->presence($operator)],
            $subject->kind === Kind::Text => $this->compare($name, $operator, $this->text($condition->value)),
            $subject->kind === Kind::Number => $this->number($name, $operator, $condition),
            $subject->kind === Kind::Date, $subject->kind === Kind::Moment => $this->date($name, $operator, $condition),
            $subject->kind === Kind::Pages && $operator === Operator::AtLeast => $this->compare("{$name}.count", $operator, $this->digits($condition->value)),
            $subject->kind === Kind::Template, $subject->kind === Kind::Status => $this->compare($name, $operator, array_key_exists($condition->value, $subject->options) ? $condition->value : null),
            default => $this->compare($name, $operator, $condition->pages === [] ? null : implode('|', $condition->pages)),
        };
    }

    /** Blank, filled, on and off: comparisons without a value of their own. */
    private function presence(Operator $operator): string
    {
        return match ($operator) {
            Operator::Blank => '=',
            Operator::On => '=1',
            Operator::Off => '!=1',
            default => '!=',
        };
    }

    /**
     * @return list<string>
     */
    private function number(string $name, Operator $operator, Condition $condition): array
    {
        if ($operator !== Operator::Between) {
            return $this->compare($name, $operator, $this->numeric($condition->value));
        }

        $from = $this->numeric($condition->value);
        $to = $this->numeric($condition->to);

        return $from === null || $to === null ? [] : ["{$name}>={$from}", "{$name}<={$to}"];
    }

    /**
     * Days are whole: "on" a day and "between" two days take the day's every hour, and "after" a
     * day starts the next one.
     *
     * @return list<string>
     */
    private function date(string $name, Operator $operator, Condition $condition): array
    {
        if ($operator === Operator::LastDays) {
            $days = $this->digits($condition->value);

            return $days === null ? [] : ["{$name}>=-{$days} days"];
        }

        $from = $this->day($condition->value);
        $to = $operator === Operator::Between ? $this->day($condition->to) : $from;

        if ($from === null || $to === null) {
            return [];
        }

        $next = $to->addDay()->format(self::DATE);
        $day = $from->format(self::DATE);

        return match ($operator) {
            Operator::Before => ["{$name}<{$day}"],
            Operator::After => ["{$name}>={$next}"],
            default => ["{$name}>={$day}", "{$name}<{$next}"],
        };
    }

    /**
     * @return list<string>
     */
    private function compare(string $name, Operator $operator, ?string $value): array
    {
        $symbol = $operator->selectorOperator();

        return $value === null || $symbol === null ? [] : [$name.$symbol.$value];
    }

    private function text(string $value): ?string
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $value));

        return $text === '' ? null : "\"{$text}\"";
    }

    private function numeric(string $value): ?string
    {
        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 ? $value : null;
    }

    private function digits(string $value): ?string
    {
        return preg_match('/^\d{1,6}$/', $value) === 1 ? $value : null;
    }

    private function day(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!'.self::DATE, $value);

        return $day instanceof CarbonImmutable && $day->format(self::DATE) === $value ? $day : null;
    }
}
