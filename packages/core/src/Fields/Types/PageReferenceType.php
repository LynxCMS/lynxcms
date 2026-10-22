<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\Concerns\StoresPageReferences;

/** References to other pages, ordered. Shared across languages. */
final class PageReferenceType extends BaseFieldType implements DescribesPhpType, PresentsValue, ReferencesPages
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
        return 'References to other pages, e.g. related courses. Setting "multiple" allows a list; "templates" limits which templates can be chosen; "input" is search (default), or select, radios or checkboxes to list those pages. Shared across languages.';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'multiple' => $schema->boolean()->description('Allow more than one page. Default true.'),
            'templates' => $schema->array()->items($schema->string())->description('Template names allowed as targets.'),
            'input' => $schema->string()->enum(self::INPUTS)->description('How editors choose: search by title (default), or select, radios or checkboxes listing the allowed pages. Lists suit short ones, such as categories.'),
        ];
    }

    public function settingsRules(): array
    {
        return ['multiple' => ['sometimes', 'boolean'], 'templates' => ['sometimes', 'array'], 'templates.*' => ['string'], 'input' => ['sometimes', 'in:'.implode(',', self::INPUTS)]];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'array'];
    }
}
