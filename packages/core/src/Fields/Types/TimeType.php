<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use DateTimeInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;

/**
 * A time of day without a date, such as an opening hour. Shared across languages. Stored as
 * "HH:MM" (or "HH:MM:SS" with the "seconds" setting), zero padded so text comparison orders it:
 * opens<12:00. Templates get the same string.
 */
final class TimeType extends BaseFieldType implements DescribesPhpType
{
    /** "9:05", "9h05", "9.05", optionally with seconds. */
    private const string WRITTEN = '/^(\d{1,2})\s*[:h.]\s*(\d{2})(?:\s*[:.]\s*(\d{2}))?$/i';

    private const string STORED = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    public static function name(): string
    {
        return 'time';
    }

    public static function description(): string
    {
        return 'A time of day without a date, e.g. 09:30. Stored as "HH:MM", or "HH:MM:SS" with "seconds": true. Shared across languages. Selectors compare zero-padded times: opens<12:00.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', 8)->nullable();
    }

    public function comparison(): Comparison
    {
        return Comparison::Text;
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return ['seconds' => $schema->boolean()->description('Also keep seconds. Default false.')];
    }

    public function settingsRules(): array
    {
        return ['seconds' => ['sometimes', 'boolean']];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'string', 'regex:'.self::STORED];
    }

    /** Zero padded "HH:MM[:SS]"; text that is not a time is kept so validation names it. */
    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || preg_match(self::WRITTEN, trim($value), $parts) !== 1) {
            return $value;
        }

        $time = sprintf('%02d:%s', (int) $parts[1], $parts[2]);

        return ($settings['seconds'] ?? false) === true ? $time.':'.($parts[3] ?? '00') : $time;
    }

    public function wake(array $rows, array $settings): mixed
    {
        $data = $rows === [] ? null : $rows[0]->data;

        return is_string($data) && $data !== '' ? $data : null;
    }

    public function phpType(Field $field): string
    {
        return 'string|null';
    }
}
