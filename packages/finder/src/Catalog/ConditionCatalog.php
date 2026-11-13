<?php

declare(strict_types=1);

namespace LynxCms\Finder\Catalog;

use LynxCms\Admin\AdminBranches;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\ChoiceOptions;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\ChoiceType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * What a finder condition can look at: the page's template, place in the tree, status and dates,
 * then every field selectors can compare (Comparison::None fields, such as passwords, are left
 * out; fields of items, such as images and repeaters, are compared by how many items they hold;
 * choice fields offer their options). Each comes with the operators its kind offers.
 *
 * @api
 */
final class ConditionCatalog
{
    /** The statuses a page can be found by, as selectors name them. */
    public const array STATUSES = ['hidden', 'unpublished', 'locked'];

    /** Page times, by the key a search stores. */
    private const array MOMENTS = ['created', 'modified', 'published'];

    /** @var array<string, Subject>|null */
    private ?array $subjects = null;

    public function __construct(private readonly AdminBranches $branches) {}

    public function find(string $key): ?Subject
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The page's own properties, in the order the finder lists them.
     *
     * @return list<Subject>
     */
    public function properties(): array
    {
        return array_values(array_filter($this->all(), fn (Subject $subject): bool => ! $this->isField($subject)));
    }

    /**
     * The fields, by label.
     *
     * @return list<Subject>
     */
    public function fields(): array
    {
        return array_values(array_filter($this->all(), $this->isField(...)));
    }

    /**
     * The fields the results can show as columns: all but fields of items, which have no short text.
     *
     * @return list<Subject>
     */
    public function columns(): array
    {
        return array_values(array_filter($this->fields(), fn (Subject $subject): bool => $subject->kind !== Kind::Items));
    }

    /**
     * The chosen columns the results can show, in the order chosen.
     *
     * @param  list<string>  $keys
     * @return list<Subject>
     */
    public function chosenColumns(array $keys): array
    {
        $offered = [];

        foreach ($this->columns() as $subject) {
            $offered[$subject->key] = $subject;
        }

        return array_values(array_filter(array_map(fn (string $key): ?Subject => $offered[$key] ?? null, $keys)));
    }

    /**
     * @return array<string, Subject>
     */
    public function all(): array
    {
        if ($this->subjects !== null) {
            return $this->subjects;
        }

        $subjects = [
            'template' => new Subject('template', $this->label('template'), Kind::Template, 'template', $this->templates()),
            'parent' => new Subject('parent', $this->label('parent'), Kind::Parent, 'has_parent'),
            'status' => new Subject('status', $this->label('status'), Kind::Status, 'status', $this->statuses()),
        ];

        foreach (self::MOMENTS as $moment) {
            $subjects[$moment] = new Subject($moment, $this->label($moment), Kind::Moment, $moment);
        }

        foreach ($this->fieldSubjects() as $subject) {
            $subjects[$subject->key] ??= $subject;
        }

        return $this->subjects = $subjects;
    }

    private function isField(Subject $subject): bool
    {
        return ! in_array($subject->kind, [Kind::Template, Kind::Parent, Kind::Status, Kind::Moment], true);
    }

    /**
     * The fields of the searched templates, by label. Each names the templates that have it when
     * some searched templates lack it, so a condition that a missing value would match ("is not",
     * "is blank") keeps to pages that can hold one.
     *
     * @return list<Subject>
     */
    private function fieldSubjects(): array
    {
        $searched = array_keys($this->searchedTemplates());
        $holders = [];

        foreach ($searched as $template) {
            foreach (array_keys(lynx()->templates->fields($template)->all()) as $field) {
                $holders[$field][] = $template;
            }
        }

        $subjects = [];

        foreach (lynx()->fields->all() as $field) {
            $name = Value::string($field->name);
            $kind = isset($holders[$name]) ? $this->kindOf($field) : null;

            if ($kind instanceof Kind) {
                $templates = count($holders[$name]) < count($searched) ? $holders[$name] : [];
                $subjects[] = new Subject($name, Value::string($field->label) ?: $name, $kind, $name, $kind === Kind::Choice ? $this->options($field) : [], $templates);
            }
        }

        usort($subjects, fn (Subject $a, Subject $b): int => strnatcasecmp($a->label, $b->label));

        return $subjects;
    }

    private function kindOf(Field $field): ?Kind
    {
        $type = $field->fieldType();

        // Items can hold page references too, but are found by how many items they hold.
        if ($type instanceof ItemFieldType) {
            return Kind::Items;
        }

        if ($type instanceof ReferencesPages) {
            return Kind::Pages;
        }

        if ($type instanceof ChoiceType) {
            return Kind::Choice;
        }

        return match ($type->comparison()) {
            Comparison::Text => Kind::Text,
            Comparison::Integer, Comparison::Decimal => Kind::Number,
            Comparison::Boolean => Kind::Boolean,
            Comparison::Date => Kind::Date,
            Comparison::None => null,
        };
    }

    /**
     * A choice field's option labels by value, in the current language.
     *
     * @return array<string, string>
     */
    private function options(Field $field): array
    {
        return array_map(fn (string|array $label): string => LocalizedText::from($label)->in(), ChoiceOptions::labels($field->config()['options'] ?? []));
    }

    /**
     * Template labels by name, for the searched templates.
     *
     * @return array<string, string>
     */
    private function templates(): array
    {
        $templates = array_map(fn (Template $template): string => Value::string($template->label) ?: Value::string($template->name), $this->searchedTemplates());
        asort($templates, SORT_NATURAL | SORT_FLAG_CASE);

        return $templates;
    }

    /**
     * The templates whose pages the finder searches, by name: all but those of module branches
     * (menus, forms). A branch holds its root templates and every template whose pages may only
     * sit under the branch's pages (or under each other), such as menu items.
     *
     * @return array<string, Template>
     */
    private function searchedTemplates(): array
    {
        $all = [];

        foreach (lynx()->templates->all() as $template) {
            $all[Value::string($template->name)] = $template;
        }

        $branch = $this->branches->rootTemplates();

        do {
            $found = array_filter($all, fn (Template $template, string $name): bool => ! in_array($name, $branch, true)
                && $template->parentTemplates() !== [] && array_diff($template->parentTemplates(), [...$branch, $name]) === [], ARRAY_FILTER_USE_BOTH);
            $branch = [...$branch, ...array_keys($found)];
        } while ($found !== []);

        return array_diff_key($all, array_flip($branch));
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        $statuses = [];

        foreach (self::STATUSES as $status) {
            $statuses[$status] = Value::string(__("lynx-finder::admin.statuses.{$status}"));
        }

        return $statuses;
    }

    private function label(string $key): string
    {
        return Value::string(__("lynx-finder::admin.subjects.{$key}"));
    }
}
