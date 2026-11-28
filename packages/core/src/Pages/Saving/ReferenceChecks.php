<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Value;

/**
 * The pages a save adds to a page field or a rich text field's links, checked on every save:
 *
 * - they must be pages the actor may view, since the admin and the tools show the title and
 *   address of what a field holds;
 * - they must use one of the templates the field's use allows ("templates": the field's, or the
 *   template's or block type's own, ADR-0094, ADR-0095).
 *
 * Pages the field already holds are kept, so narrowing a setting or a role never blocks an
 * unrelated save. Inside repeater and matrix items, each page subfield is checked with its own
 * settings.
 *
 * @internal
 */
final readonly class ReferenceChecks
{
    public function __construct(private PageLoader $loader, private Authorizer $authorizer, private ActionRunner $runner) {}

    /**
     * @param  array<string, mixed>  $settings  the field's settings where it is used
     * @return array<string, string> errors keyed under $key
     */
    public function errors(?int $pageId, Field $field, array $settings, mixed $value, string $key): array
    {
        $type = $field->fieldType();

        if (! $type instanceof ReferencesPages) {
            return [];
        }

        $scope = $this->authorizer->scopeOf($this->runner->actor(), 'pages.view');

        if ($scope === null && ! $this->limitsTemplates($type, $settings)) {
            return [];
        }

        $stored = $pageId === null ? [] : $this->loader->fieldValues($field->name, [$pageId])[$pageId] ?? [];

        if ($type instanceof ItemFieldType) {
            return $this->itemErrors($type, $settings, $value, $stored, $scope, $key);
        }

        $held = [];

        foreach ($stored as $one) {
            array_push($held, ...$type->pageIds($one, $settings));
        }

        $problem = $this->problem(array_diff($type->pageIds($value, $settings), $held), Value::strings($settings['templates'] ?? []), $scope);

        return $problem === null ? [] : [$key => $problem];
    }

    /**
     * Whether the use, or for items one of their subfields, allows only some templates.
     *
     * @param  array<string, mixed>  $settings
     */
    private function limitsTemplates(ReferencesPages $type, array $settings): bool
    {
        if (! $type instanceof ItemFieldType) {
            return Value::strings($settings['templates'] ?? []) !== [];
        }

        return array_any($type->allSubfields($settings), fn (Subfield $subfield): bool => Value::strings($subfield->config()['templates'] ?? []) !== []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, mixed>  $stored  language id => the items stored
     * @return array<string, string>
     */
    private function itemErrors(ItemFieldType $type, array $settings, mixed $value, array $stored, ?PageScope $scope, string $key): array
    {
        $errors = [];
        $storedItems = [];

        foreach ($stored as $items) {
            array_push($storedItems, ...Value::list($items));
        }

        foreach (Value::list($value) as $index => $item) {
            $item = Value::array($item);

            foreach ($type->subfields($settings, Value::nullableString($item['type'] ?? null)) as $subfield) {
                $subType = $subfield->type();
                $templates = Value::strings($subfield->config()['templates'] ?? []);

                if (! $subType instanceof ReferencesPages || $subType instanceof ItemFieldType || ($templates === [] && $scope === null)) {
                    continue;
                }

                $name = $subfield->name();
                $held = [];

                foreach ($storedItems as $storedItem) {
                    array_push($held, ...$subType->pageIds(Value::array(Value::array($storedItem)['values'] ?? [])[$name] ?? null, $subfield->config()));
                }

                $problem = $this->problem(array_diff($subType->pageIds(Value::array($item['values'] ?? [])[$name] ?? null, $subfield->config()), $held), $templates, $scope);

                if ($problem !== null) {
                    $errors["{$key}.{$index}.{$name}"] = $problem;
                }
            }
        }

        return $errors;
    }

    /**
     * Why the added pages can't be held, or null. A page the actor may not view, or that does not
     * exist, is named by its id only.
     *
     * @param  array<int|string, int>  $added
     * @param  list<string>  $templates  empty allows any
     * @param  PageScope|null  $scope  the pages the actor may view; null for all
     */
    private function problem(array $added, array $templates, ?PageScope $scope): ?string
    {
        $added = array_values(array_unique($added));

        if ($added === []) {
            return null;
        }

        $hidden = $scope === null ? [] : array_values(array_diff($added, $scope->covering($added)));

        if ($hidden !== []) {
            return Value::string(__('lynx::validation.values.page_not_viewable', ['id' => $hidden[0]]));
        }

        return $templates === [] ? null : $this->templateProblem($added, $templates);
    }

    /**
     * @param  list<int>  $added
     * @param  list<string>  $templates
     */
    private function templateProblem(array $added, array $templates): ?string
    {
        foreach ($this->loader->many($added) as $page) {
            if (! in_array($page->template()->name, $templates, true)) {
                $name = $page->name() === '' ? '/' : $page->name();

                return Value::string(__('lynx::validation.values.page_template', ['templates' => implode(Value::string(__('lynx::validation.or')), $templates), 'page' => $name, 'template' => $page->template()->name]));
            }
        }

        return null;
    }
}
