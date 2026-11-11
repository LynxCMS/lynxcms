<?php

declare(strict_types=1);

namespace LynxCms\Finder\Catalog;

use LynxCms\Admin\AdminBranches;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;

/**
 * What a finder condition can look at: the page's template, place in the tree, status and dates,
 * then every field selectors can compare (Comparison::None fields, such as passwords, are left
 * out; fields of items, such as images and repeaters, are compared by how many items they hold). Each comes with the operators its kind offers.
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
     * The fields of the searched templates, by label.
     *
     * @return list<Subject>
     */
    private function fieldSubjects(): array
    {
        $used = [];

        foreach (array_keys($this->searchedTemplates()) as $template) {
            $used += lynx()->templates->fields($template)->all();
        }

        $subjects = [];

        foreach (lynx()->fields->all() as $field) {
            $name = Value::string($field->name);
            $kind = isset($used[$name]) ? $this->kindOf($field) : null;

            if ($kind instanceof Kind) {
                $subjects[] = new Subject($name, Value::string($field->label) ?: $name, $kind, $name);
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

        return match ($type->comparison()) {
            Comparison::Text => Kind::Text,
            Comparison::Integer, Comparison::Decimal => Kind::Number,
            Comparison::Boolean => Kind::Boolean,
            Comparison::Date => Kind::Date,
            Comparison::None => null,
        };
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
