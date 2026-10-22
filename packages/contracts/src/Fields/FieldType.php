<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\JsonSchema\Types\Type;
use stdClass;

/**
 * A field type owns its storage, its value conversion and its validation. Modules register new
 * types; the core never needs to know what a type stores (ADR-0004, ADR-0010).
 *
 * @api
 */
interface FieldType
{
    /** Machine name, e.g. "text". */
    public static function name(): string;

    /** One sentence for editors and AI agents. */
    public static function description(): string;

    /** Whether fields of this type may be translatable. */
    public function supportsTranslation(): bool;

    /** Whether a value is a list stored as several rows with a "sort" column. */
    public function multiple(): bool;

    /**
     * Add the value column(s) to a field table. The table already has page_id,
     * language_id (translatable fields) and sort (multiple fields).
     *
     * @param  array<string, mixed>  $settings
     */
    public function columns(Blueprint $table, array $settings): void;

    /** Whether the "data" column can be indexed and compared in selectors. */
    public function indexable(): bool;

    /** How selectors compare the "data" column. */
    public function comparison(): Comparison;

    /**
     * Type-specific settings an editor or AI agent can set (max length, options...).
     *
     * @return array<string, Type>
     */
    public function settingsSchema(JsonSchema $schema): array;

    /**
     * Validation rules for the type-specific settings.
     *
     * @return array<string, mixed>
     */
    public function settingsRules(): array;

    /**
     * Validation rules for one value.
     *
     * @param  array<string, mixed>  $settings
     * @return list<mixed>
     */
    public function rules(array $settings): array;

    /**
     * Clean a value before every save, whatever the caller (admin, API, MCP, AI).
     *
     * @param  array<string, mixed>  $settings
     */
    public function normalize(mixed $value, array $settings): mixed;

    /** Whether a normalized value counts as empty (an explicit empty row). */
    public function isEmpty(mixed $value): bool;

    /**
     * Convert a value to the column values of its row(s).
     *
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    public function sleep(mixed $value, array $settings): array;

    /**
     * Convert stored row(s) back to a value.
     *
     * @param  list<stdClass>  $rows
     * @param  array<string, mixed>  $settings
     */
    public function wake(array $rows, array $settings): mixed;

    /**
     * Whether values are trusted HTML, so every template engine prints them unescaped.
     *
     * @param  array<string, mixed>  $settings
     */
    public function isHtml(array $settings): bool;
}
