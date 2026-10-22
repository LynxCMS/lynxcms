<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;
use Throwable;

/**
 * A date, or a date and time with the "time" setting. Shared across languages. Stored as a
 * datetime (midnight for dates), so switching "time" on later needs no change to the table.
 * Templates get a CarbonImmutable: $page->starts_on->isoFormat('D MMMM YYYY').
 */
final class DateType extends BaseFieldType implements DescribesPhpType
{
    public const string STORAGE_FORMAT = 'Y-m-d H:i:s';

    public static function name(): string
    {
        return 'date';
    }

    public static function description(): string
    {
        return 'A date, or a date and time with "time": true. Shared across languages. Selectors compare dates: starts_on>=today, starts_on<2026-01-01.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->dateTime('data')->nullable();
    }

    public function comparison(): Comparison
    {
        return Comparison::Date;
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return ['time' => $schema->boolean()->description('Also store the time of day. Default false.')];
    }

    public function settingsRules(): array
    {
        return ['time' => ['sometimes', 'boolean']];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'date'];
    }

    /** A date string in the storage format; text that is not a date is kept so validation names it. */
    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse(is_string($value) ? trim($value) : '');
        } catch (Throwable) {
            return $value;
        }

        return (($settings['time'] ?? false) === true ? $date : $date->startOfDay())->format(self::STORAGE_FORMAT);
    }

    public function wake(array $rows, array $settings): mixed
    {
        $data = $rows === [] ? null : $rows[0]->data;

        return is_string($data) && $data !== '' ? CarbonImmutable::parse($data) : null;
    }

    public function phpType(Field $field): string
    {
        return '\\'.CarbonImmutable::class.'|null';
    }
}
