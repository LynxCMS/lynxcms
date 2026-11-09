<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Templates\FieldPlacement;
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

    public function __construct(private TemplateRegistry $templates, private FieldSnippet $snippets, private FieldPlacement $placement) {}

    /** @param  string|null  $layout  a Blade component such as "layouts.site", given $page */
    public function for(Template $template, ?string $layout = null, bool $allTabs = false): string
    {
        $lines = ['{{-- '.$template->labels()->in().' ('.$template->name.'): written by lynx:make:view, yours to change. --}}'];
        $section = null;
        $elsewhere = [];

        foreach ($this->templates->fields($template) as $templateField) {
            $tab = $this->placement->tab($template, $templateField);

            if (! $allTabs && $tab !== FieldPlacement::CONTENT_TAB) {
                $elsewhere[$tab][] = $templateField->name();

                continue;
            }

            $label = $this->placement->sectionLabel($template, $templateField);

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

    /**
     * @param  list<string>  $lines
     */
    private function wrap(array $lines, ?string $layout): string
    {
        $body = array_map(fn (string $line): string => $line === '' ? '' : self::INDENT.$line, $lines);
        [$open, $close] = $layout === null ? ['<main>', '</main>'] : ["<x-{$layout} :page=\"\$page\">", "</x-{$layout}>"];

        return implode("\n", [$open, ...$body, $close])."\n";
    }
}
