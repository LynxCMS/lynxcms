<?php

declare(strict_types=1);

namespace LynxCms\Forms\Transfer;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormSchema;

/**
 * A builder form's structure as data, for JSON (ADR-0146): its name, title, messages and the
 * blocks of its fields, with texts keyed by language code and the code of the site's default
 * language, so another site can key them by its own. Recipients, the one answer per account
 * setting and submissions belong to the site, and stay out.
 *
 * @api
 */
final readonly class FormExporter
{
    /** The version of the format, which an import checks. */
    public const int FORMAT = 1;

    /** The messages a form has, by their key in the file. */
    public const array MESSAGES = ['success' => FormSchema::SUCCESS, 'error' => FormSchema::ERROR, 'auto_reply' => FormSchema::AUTO_REPLY];

    public function __construct(private FormRegistry $forms) {}

    /**
     * @return array{lynx_form: int, name: string, default_language: string, title: array<string, string>|null, messages: array<string, array<string, string>|null>, fields: list<array<string, mixed>>}
     *
     * @throws FormTransferError
     */
    public function export(string $name): array
    {
        $page = $this->page($name);

        return [
            'lynx_form' => self::FORMAT,
            'name' => $name,
            'default_language' => (string) lynx()->languages->default()->code,
            'title' => $this->texts($page, 'title'),
            'messages' => array_map(fn (string $field): ?array => $this->texts($page, $field), self::MESSAGES),
            'fields' => array_map(
                fn (mixed $block): array => array_intersect_key(Value::map($block), array_flip(['type', 'depth', 'enabled', 'values'])),
                Value::list($page->getUnformatted(FormSchema::FIELDS)),
            ),
        ];
    }

    /** The form's JSON, as lynx:forms:export and the admin's download write it. */
    public function toJson(string $name): string
    {
        return json_encode($this->export($name), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    private function page(string $name): Page
    {
        $page = preg_match(FormSchema::NAME_PATTERN, $name) === 1 && lynx()->templates->find(FormSchema::FORM_TEMPLATE) !== null
            ? lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name='.$name.', include=all')
            : null;

        if ($page instanceof Page) {
            return $page;
        }

        throw new FormTransferError($this->forms->get($name) !== null
            ? "Form [{$name}] is defined in code, which travels with the app's code. Only forms built in the admin are exported."
            : "There is no form [{$name}] built in the admin.");
    }

    /**
     * The field's own text in each language, without fallback; null when it has none.
     *
     * @return array<string, string>|null
     */
    private function texts(Page $page, string $field): ?array
    {
        $texts = [];

        foreach (lynx()->languages->all() as $language) {
            $text = $page->templateField($field) === null ? null : $page->getUnformatted($field, $language);

            if (is_string($text) && $text !== '' && $language instanceof Language) {
                $texts[(string) $language->code] = $text;
            }
        }

        return $texts === [] ? null : $texts;
    }
}
