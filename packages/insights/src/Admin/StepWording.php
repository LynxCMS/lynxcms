<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Value;

/**
 * A plan step in the reader's language, for the review: translations name the language, saves name
 * the fields they change by their labels, and any other action is named as it is registered. The
 * actions' own change summaries are written in English for developers (ADR-0141), so the review
 * does not show them.
 *
 * @internal
 */
final readonly class StepWording
{
    public function __construct(private LanguageRegistry $languages, private FieldRegistry $fields) {}

    /**
     * @param  array{action: string, arguments: array<string, mixed>}|array<string, mixed>  $step
     */
    public function describe(array $step): string
    {
        $action = Value::string($step['action'] ?? '');
        $arguments = Value::array($step['arguments'] ?? []);

        return Value::string(match ($action) {
            'pages.translate' => __('lynx-insights::admin.plan.translate', ['language' => $this->language(Value::string($arguments['to'] ?? ''))]),
            'pages.save' => __('lynx-insights::admin.plan.save', ['fields' => $this->fieldLabels(array_keys(Value::array($arguments['values'] ?? [])))]),
            default => __('lynx-insights::admin.plan.other', ['action' => $action]),
        });
    }

    private function language(string $code): string
    {
        return $this->languages->find($code)->name ?? $code;
    }

    /** @param  list<int|string>  $names */
    private function fieldLabels(array $names): string
    {
        return implode(', ', array_map(fn (int|string $name): string => Value::string($this->fields->find((string) $name)?->label, (string) $name), $names));
    }
}
