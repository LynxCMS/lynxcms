<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;

/**
 * What a pages.save would change on a page, field by field and language by language: the editor's
 * Review changes, and later the agent's plans. Fields the template does not have are left out;
 * pages.save itself reports them.
 *
 * @api
 */
final readonly class ChangeReview
{
    public function __construct(private FieldViews $views) {}

    /**
     * @param  array<string, mixed>  $values  the "values" input of pages.save
     * @return list<ReviewEntry>
     */
    public function forSave(Page $page, array $values): array
    {
        $entries = [];

        foreach ($values as $name => $value) {
            $templateField = $page->templateField((string) $name);

            if ($templateField !== null) {
                array_push($entries, ...$this->entries($page, $templateField, $value));
            }
        }

        return $entries;
    }

    /**
     * @return list<ReviewEntry>
     */
    private function entries(Page $page, TemplateField $templateField, mixed $value): array
    {
        $isTranslatable = $templateField->field->isTranslatable();
        $perLanguage = $isTranslatable && is_array($value) ? $value : [Value::string(lynx()->languages->default()->code) => $value];
        $entries = [];

        foreach ($perLanguage as $code => $after) {
            $language = lynx()->languages->find((string) $code);

            if ($language instanceof Language) {
                $entries[] = new ReviewEntry(
                    $templateField->name(),
                    $templateField->label(),
                    $isTranslatable ? Value::string($language->name) : null,
                    (string) $templateField->field->type,
                    $this->views->display($templateField, $page->getLanguageValue($language, $templateField->name())),
                    $this->views->display($templateField, $after),
                );
            }
        }

        return $entries;
    }
}
