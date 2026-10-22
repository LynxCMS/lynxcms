<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * Where a template's fields appear in the page editor: tabs, then a main and a sidebar column,
 * each a list of fields and sections. The field order stays one flat list (values, selectors and
 * templates->fields() never see the layout); a section sits where its first field is.
 *
 * @api
 */
final readonly class TemplateLayout
{
    public const string MAIN = 'main';

    public const string SIDEBAR = 'sidebar';

    public const array COLUMNS = [self::MAIN, self::SIDEBAR];

    /**
     * @param  list<LayoutTab>  $tabs
     * @param  array<string, LayoutSection>  $sections
     * @param  array<string, array<string, list<LayoutBlock>>>  $columns  tab => column => blocks
     * @param  array<string, string>  $fieldTabs  field name => tab key
     */
    private function __construct(
        private array $tabs,
        private array $sections,
        private array $columns,
        private array $fieldTabs,
        private bool $hasLayout,
    ) {}

    public static function for(Template $template): self
    {
        $settings = $template->config();
        $tabs = [new LayoutTab(LayoutTab::CONTENT, LocalizedText::from('Content')), ...self::tabsFrom($settings['tabs'] ?? [])];
        $tabKeys = array_map(fn (LayoutTab $tab): string => $tab->key, $tabs);
        $sections = self::sectionsFrom($settings['sections'] ?? [], $tabKeys);
        [$columns, $fieldTabs, $placed] = self::place(lynx()->templates->fields($template)->all(), $sections, $tabKeys);

        return new self($tabs, $sections, $columns, $fieldTabs, $placed || $sections !== [] || count($tabs) > 1);
    }

    /** Whether the template sets any tab, section or column; without one the editor stays a single list. */
    public function hasLayout(): bool
    {
        return $this->hasLayout;
    }

    /**
     * @return list<LayoutTab> "content" first, then the template's own tabs
     */
    public function tabs(): array
    {
        return $this->tabs;
    }

    /**
     * @return list<LayoutBlock>
     */
    public function blocks(string $tab, string $column = self::MAIN): array
    {
        return $this->columns[$tab][$column] ?? [];
    }

    public function hasSidebar(string $tab): bool
    {
        return $this->blocks($tab, self::SIDEBAR) !== [];
    }

    public function section(string $key): ?LayoutSection
    {
        return $this->sections[$key] ?? null;
    }

    /** The tab a field appears in. */
    public function tabOf(string $field): string
    {
        return $this->fieldTabs[$field] ?? LayoutTab::CONTENT;
    }

    /**
     * @return list<LayoutTab>
     */
    private static function tabsFrom(mixed $tabs): array
    {
        return array_map(
            fn (mixed $tab): LayoutTab => new LayoutTab(Value::string(Value::map($tab)['key'] ?? ''), LocalizedText::from(Value::map($tab)['label'] ?? '')),
            Value::list($tabs),
        );
    }

    /**
     * @param  list<string>  $tabKeys
     * @return array<string, LayoutSection>
     */
    private static function sectionsFrom(mixed $sections, array $tabKeys): array
    {
        $result = [];

        foreach (Value::list($sections) as $section) {
            $section = Value::map($section);
            $key = Value::string($section['key'] ?? '');
            $result[$key] = new LayoutSection(
                $key,
                LocalizedText::from($section['label'] ?? $key),
                self::known(Value::string($section['tab'] ?? ''), $tabKeys, LayoutTab::CONTENT),
                self::known(Value::string($section['column'] ?? ''), self::COLUMNS, self::MAIN),
                (bool) ($section['collapsed'] ?? false),
            );
        }

        return $result;
    }

    /**
     * Each field in its tab and column, a section where its first field is; empty sections last.
     *
     * @param  array<string, TemplateField>  $fields
     * @param  array<string, LayoutSection>  $sections
     * @param  list<string>  $tabKeys
     * @return array{0: array<string, array<string, list<LayoutBlock>>>, 1: array<string, string>, 2: bool}
     */
    private static function place(array $fields, array $sections, array $tabKeys): array
    {
        $columns = [];
        $blocks = [];
        $fieldTabs = [];
        $placed = false;

        foreach ($fields as $name => $templateField) {
            $section = $sections[Value::string($templateField->settings['section'] ?? '')] ?? null;

            if ($section instanceof LayoutSection) {
                if (! isset($blocks[$section->key])) {
                    $blocks[$section->key] = LayoutBlock::section($section);
                    $columns[$section->tab][$section->column][] = $blocks[$section->key];
                }

                $blocks[$section->key]->fields[] = $name;
                $fieldTabs[$name] = $section->tab;

                continue;
            }

            $tab = self::known(Value::string($templateField->settings['tab'] ?? ''), $tabKeys, LayoutTab::CONTENT);
            $column = self::known(Value::string($templateField->settings['column'] ?? ''), self::COLUMNS, self::MAIN);
            $placed = $placed || $tab !== LayoutTab::CONTENT || $column !== self::MAIN;
            $columns[$tab][$column][] = LayoutBlock::field((string) $name);
            $fieldTabs[(string) $name] = $tab;
        }

        foreach (array_diff_key($sections, $blocks) as $section) {
            $columns[$section->tab][$section->column][] = LayoutBlock::section($section);
        }

        return [$columns, $fieldTabs, $placed];
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function known(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
