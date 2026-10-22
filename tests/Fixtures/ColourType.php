<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Core\Fields\Types\BaseFieldType;

/** A module field type without an admin input, to test the read-only fallback. */
final class ColourType extends BaseFieldType
{
    public static function name(): string
    {
        return 'colour';
    }

    public static function description(): string
    {
        return 'A colour such as #ff0000.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', 16)->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [];
    }
}
