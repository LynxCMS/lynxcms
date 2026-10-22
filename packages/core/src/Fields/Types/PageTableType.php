<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\NeedsSavedPage;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\Concerns\StoresPageReferences;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;

/**
 * Pages made for this page and listed in a table, like ProcessWire's Page Table (ADR-0058): a
 * course's sessions, an event's speakers. Each item is a full page of one of the "templates",
 * created under the page itself (or the "parent" path) and hidden from navigation. The field holds
 * them in order; templates read a PageArray. Removing an item from the list keeps its page unless
 * "remove" is "trash".
 */
final class PageTableType extends BaseFieldType implements DescribesPhpType, NeedsSavedPage, PresentsValue, ReferencesPages
{
    use StoresPageReferences;

    /** What removing an item from the table does to its page. */
    public const array REMOVE = ['keep', 'trash'];

    public static function name(): string
    {
        return 'page_table';
    }

    public static function description(): string
    {
        return 'Pages made for this page and listed in a table, e.g. a course\'s sessions. Setting "templates" (required) names the templates of the items; "parent" is the path new items go under (default: the page itself); "columns" lists the fields the admin table shows; "remove" is keep (default) or trash. Create items with pages_save, then list their ids in order. Shared across languages.';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'templates' => $schema->array()->items($schema->string())->description('Templates of the items. At least one.')->required(),
            'parent' => $schema->string()->description('Path new items are created under. Default: the page itself.'),
            'columns' => $schema->array()->items($schema->string())->description('Fields shown as columns in the admin table. Default: title.'),
            'remove' => $schema->string()->enum(self::REMOVE)->description('Removing an item from the table keeps its page (default) or trashes it.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'templates' => ['required', 'array', 'min:1'],
            'templates.*' => ['string'],
            'parent' => ['sometimes', 'nullable', 'string'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string'],
            'remove' => ['sometimes', 'in:'.implode(',', self::REMOVE)],
        ];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'array', $this->allowedTemplates(Value::strings($settings['templates'] ?? []))];
    }

    /**
     * Every item must use one of the field's templates.
     *
     * @param  list<string>  $templates
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private function allowedTemplates(array $templates): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($templates): void {
            foreach (app(PageLoader::class)->many(Value::ints((array) $value)) as $page) {
                if ($page instanceof Page && ! in_array($page->template()->name, $templates, true)) {
                    $fail("Page [{$page->name()}] uses template [{$page->template()->name}]; this table holds ".implode(', ', $templates).' pages.');
                }
            }
        };
    }
}
