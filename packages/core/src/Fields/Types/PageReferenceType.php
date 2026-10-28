<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\SettingsPerUse;
use LynxCms\Core\Fields\Types\Concerns\StoresPageReferences;
use LynxCms\Core\Support\Value;

/** References to other pages, ordered. Shared across languages. */
final class PageReferenceType extends BaseFieldType implements DescribesPhpType, PresentsValue, ReferencesPages, SettingsPerUse
{
    use StoresPageReferences;

    /** How editors choose: a title search, or a list of the pages the "templates" setting allows. */
    public const array INPUTS = ['search', 'select', 'radios', 'checkboxes'];

    public static function name(): string
    {
        return 'page';
    }

    public static function description(): string
    {
        return 'References to other pages, e.g. related courses. Setting "multiple" allows a list, and "min" and "max" bound how many pages it holds; "templates" limits which templates can be chosen; "input" is search (default), or select, radios or checkboxes to list those pages. Shared across languages.';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'multiple' => $schema->boolean()->description('Allow more than one page. Default true.'),
            'min' => $schema->integer()->description('Fewest pages when the field holds any (0 = no minimum; "required" asks for at least one).'),
            'max' => $schema->integer()->description('Most pages (0 = no limit).'),
            'templates' => $schema->array()->items($schema->string())->description('Template names allowed as targets.'),
            'input' => $schema->string()->enum(self::INPUTS)->description('How editors choose: search by title (default), or select, radios or checkboxes listing the allowed pages. Lists suit short ones, such as categories.'),
        ];
    }

    /** A template or block type may set how many pages, and which templates, for its own use (ADR-0094). */
    public function settingsPerUse(): array
    {
        return ['min', 'max', 'templates'];
    }

    public function settingsRules(): array
    {
        return [
            'multiple' => ['sometimes', 'boolean'],
            'min' => ['sometimes', 'integer', 'min:0'],
            'max' => ['sometimes', 'integer', 'min:0'],
            'templates' => ['sometimes', 'array'],
            'templates.*' => ['string'],
            'input' => ['sometimes', 'in:'.implode(',', self::INPUTS)],
        ];
    }

    /** The normalized ids, counted against "min" and "max" the way repeaters count their items. */
    public function rules(array $settings): array
    {
        $min = Value::int($settings['min'] ?? 0);
        $max = Value::int($settings['max'] ?? 0);

        return ['nullable', 'array', function (string $attribute, mixed $ids, Closure $fail) use ($min, $max): void {
            $count = count(Value::ints($ids));

            if ($max > 0 && $count > $max) {
                $fail("Pick at most {$max} pages.");
            } elseif ($count > 0 && $count < $min) {
                $fail("Pick at least {$min} pages.");
            }
        }];
    }
}
