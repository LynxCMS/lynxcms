<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;

/**
 * Where a field sits in a template's editor (ADR-0030): its section, as the template stores it,
 * and its tab: its own, else its section's, else Content.
 *
 * @internal
 */
final class FieldPlacement
{
    /** The editor's main tab: fields without a tab of their own. */
    public const string CONTENT_TAB = 'content';

    /**
     * @return array<string, mixed>|null
     */
    public function section(Template $template, TemplateField $templateField): ?array
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

    public function sectionLabel(Template $template, TemplateField $templateField): ?string
    {
        $section = $this->section($template, $templateField);

        if ($section === null) {
            return null;
        }

        $label = $section['label'] ?? $section['key'] ?? null;

        return is_array($label) ? Value::string(reset($label)) : Value::string($label);
    }

    public function tab(Template $template, TemplateField $templateField): string
    {
        $own = $templateField->settings['tab'] ?? null;

        if (is_string($own) && $own !== '') {
            return $own;
        }

        $tab = $this->section($template, $templateField)['tab'] ?? null;

        return is_string($tab) && $tab !== '' ? $tab : self::CONTENT_TAB;
    }
}
