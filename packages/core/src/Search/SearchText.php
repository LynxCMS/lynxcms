<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use Normalizer;

/**
 * What the search index holds for a page in a language (ADR-0054): the words of its title and of
 * the fields its template marks searchable, read with fallback, as folded words (lowercase,
 * without accents or markup). Queries are folded the same way, so both sides compare alike.
 *
 * @internal
 */
final readonly class SearchText
{
    public function __construct(
        private TemplateRegistry $templates,
        private LanguageContext $context,
    ) {}

    public function of(Page $page, Language $language): string
    {
        return Value::string($this->context->with($language, function () use ($page, $language): string {
            $parts = [];

            foreach ($this->templates->fields($page->template()) as $name => $templateField) {
                $type = $templateField->field->fieldType();

                if (($name === 'title' || $templateField->searchable()) && $type instanceof ProvidesSearchText) {
                    $parts[] = $type->searchText($page->getUnformatted($name), $templateField->field->config(), $language);
                }
            }

            return implode(' ', self::words(implode(' ', $parts)));
        }));
    }

    /**
     * Text as folded words: markup removed, lowercase, accents stripped (when intl is installed).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (class_exists(Normalizer::class)) {
            $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
            $text = preg_replace('/\p{Mn}+/u', '', is_string($decomposed) ? $decomposed : $text) ?? $text;
        }

        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
