<?php

declare(strict_types=1);

namespace LynxCms\Admin\Fields;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Fields\Choices\ChoiceList;
use LynxCms\Core\Fields\Types\DateType;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Support\Value;
use Stringable;

/**
 * A page's field value as short text for a table cell: option labels, page titles, the date in the
 * locale's format (with the time when it has one), yes or no for a switch, and other text without
 * tags, cut to MAX_LENGTH characters. Fields the page's template lacks, and values with no short
 * form (images, repeaters), are empty.
 *
 * @api
 */
final class ShortValue
{
    public const int MAX_LENGTH = 80;

    public static function of(Page $page, string $field): string
    {
        if ($page->templateField($field) === null) {
            return '';
        }

        $value = $page->get($field);
        $isDate = lynx()->fields->find($field)?->fieldType() instanceof DateType;

        return match (true) {
            $value instanceof Choice => $value->label(),
            $value instanceof ChoiceList => implode(', ', $value->labels()),
            $value instanceof PageArray => implode(', ', $value->map(fn (Page $item): string => Value::string($item->getUnformatted('title')))),
            $value instanceof Page => Value::string($value->getUnformatted('title')),
            $value instanceof DateTimeInterface => self::date($value),
            $isDate && is_string($value) && $value !== '' => self::date(CarbonImmutable::parse($value)),
            is_bool($value) => Value::string(__($value ? 'lynx-admin::editor.value.yes' : 'lynx-admin::editor.value.no')),
            is_scalar($value), $value instanceof Stringable => mb_strimwidth(strip_tags((string) $value), 0, self::MAX_LENGTH, '…'),
            default => '',
        };
    }

    private static function date(DateTimeInterface $value): string
    {
        $date = CarbonImmutable::instance($value);

        return $date->isoFormat($date->format('H:i:s') === '00:00:00' ? 'll' : 'lll');
    }
}
