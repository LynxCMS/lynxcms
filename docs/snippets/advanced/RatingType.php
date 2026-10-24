<?php

declare(strict_types=1);

namespace App\Lynx\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;

// #region type
/**
 * A star rating from 1 to a maximum (5 by default), such as a review's score.
 */
final class RatingType extends BaseFieldType implements DescribesPhpType
{
    private const int DEFAULT_MAX = 5;

    public static function name(): string
    {
        return 'rating';
    }

    /** Read by the field form, the MCP server and AI agents to choose a type. */
    public static function description(): string
    {
        return 'A star rating from 1 to "max" (default 5), e.g. a review score. Selectors compare numbers: rating>=4.';
    }

    /** The column that stores the value, in the field's own table. */
    public function columns(Blueprint $table, array $settings): void
    {
        $table->unsignedTinyInteger('data')->nullable();
    }

    /** Selectors compare it as a number, so rating>=4 works. */
    public function comparison(): Comparison
    {
        return Comparison::Integer;
    }

    /** The field's settings, for the admin, MCP and the agent. */
    public function settingsSchema(JsonSchema $schema): array
    {
        return ['max' => $schema->integer()->description('Most stars, 3 to 10. Default 5.')];
    }

    public function settingsRules(): array
    {
        return ['max' => ['nullable', 'integer', 'between:3,10']];
    }

    /** Checked on every save, whoever saves. */
    public function rules(array $settings): array
    {
        return ['nullable', 'integer', 'between:1,'.($settings['max'] ?? self::DEFAULT_MAX)];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /** What templates read, for lynx:phpstan. */
    public function phpType(Field $field): string
    {
        return 'int|null';
    }
}
// #endregion type
