<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * A starting view for a template (lynx:make:view): its fields in the template's order, each
 * section under a comment with its label, optionally inside a layout component. Only fields on
 * the editor's Content tab are printed unless asked: other tabs usually hold what goes in the
 * page's head or settings (a module's SEO tab), so they are named in a comment instead.
 *
 * @internal
 */
final readonly class ViewStub
{
    private const string INDENT = '    ';

    /** The editor's main tab: fields without a tab of their own (ADR-0030). */
    private const string CONTENT_TAB = 'content';

    public function __construct(private TemplateRegistry $templates, private FieldSnippet $snippets) {}

    /** @param  string|null  $layout  a Blade component such as "layouts.site", given $page */
    public function for(Template $template, ?string $layout = null, bool $allTabs = false): string
    {
        $lines = ['{{-- '.$template->labels()->in().' ('.$template->name.'): written by lynx:make:view, yours to change. --}}'];
        $section = null;
        $elsewhere = [];

        foreach ($this->templates->fields($template) as $templateField) {
            $tab = $this->tab($template, $templateField);

            if (! $allTabs && $tab !== self::CONTENT_TAB) {
                $elsewhere[$tab][] = $templateField->name();

                continue;
            }

            $label = $this->sectionLabel($template, $templateField);

            if ($label !== null && $label !== $section) {
                $lines[] = '';
                $lines[] = '{{-- '.$label.' --}}';
            }

            $section = $label ?? $section;
            $lines[] = '';
            array_push($lines, ...$this->snippets->lines('$page', $templateField->field, $templateField->config()));
        }

        foreach ($elsewhere as $tab => $names) {
            $lines[] = '';
            $lines[] = '{{-- On the '.$tab.' tab, not printed here: '.implode(', ', $names).' --}}';
        }

        return $this->wrap($lines, $layout);
    }

    /** The editor tab the field shows on: its own, else its section's, else Content. */
    private function tab(Template $template, TemplateField $templateField): string
    {
        $own = $templateField->settings['tab'] ?? null;

        if (is_string($own) && $own !== '') {
            return $own;
        }

        $section = $this->section($template, $templateField);

        return is_string($section['tab'] ?? null) && $section['tab'] !== '' ? $section['tab'] : self::CONTENT_TAB;
    }

    /**
     * The section the field sits in, as the template stores it.
     *
     * @return array<string, mixed>|null
     */
    private function section(Template $template, TemplateField $templateField): ?array
    {
        $key = $templateField->settings['section'] ?? null;

        foreach (Value::list($template->config()['sections'] ?? []) as $section) {
            $section = Value::map($section);

            if ($key !== null && ($section['key'] ?? null) === $key) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function wrap(array $lines, ?string $layout): string
    {
        if ($layout === null) {
            return implode("\n", ['<main>', ...array_map(fn (string $line): string => $line === '' ? '' : self::INDENT.$line, $lines), '</main>'])."\n";
        }

        return implode("\n", ["<x-{$layout} :page=\"\$page\">", ...array_map(fn (string $line): string => $line === '' ? '' : self::INDENT.$line, $lines), "</x-{$layout}>"])."\n";
    }

    private function sectionLabel(Template $template, TemplateField $templateField): ?string
    {
        $section = $this->section($template, $templateField);

        if ($section === null) {
            return null;
        }

        $label = $section['label'] ?? $section['key'] ?? null;

        return is_array($label) ? Value::string(reset($label)) : Value::string($label);
    }
}
