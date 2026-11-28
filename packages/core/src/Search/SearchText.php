<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Search\Plurals\EndingRules;
use LynxCms\Core\Search\Plurals\WordReducer;
use LynxCms\Core\Search\Plurals\WordReducers;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;
use Normalizer;

/**
 * What the search index holds for a page in a language (ADR-0054): the words of its title and of
 * the fields its template marks searchable, read with fallback, as folded words (lowercase,
 * without accents or markup), each followed by its singular where the language has a plural
 * reducer (ADR-0119). Queries are folded and reduced the same way, so both sides compare alike.
 *
 * @internal
 */
final readonly class SearchText
{
    public function __construct(
        private TemplateRegistry $templates,
        private LanguageContext $context,
        private WordReducers $reducers,
    ) {}

    public function of(Page $page, Language $language): string
    {
        return Value::string($this->context->with($language, function () use ($page, $language): string {
            $parts = [];

            foreach ($this->templates->fields($page->template()) as $name => $templateField) {
                $type = $templateField->field->fieldType();

                if (self::indexes($name, $templateField) && $type instanceof ProvidesSearchText) {
                    $parts[] = $type->searchText($page->getUnformatted($name), $templateField->field->config(), $language);
                }
            }

            return implode(' ', self::terms(implode(' ', $parts), $this->reducers->for($language)));
        }));
    }

    /** Whether a template field's text goes into the index: the title always, else when marked searchable and its type gives text. */
    public static function indexes(string $name, TemplateField $templateField): bool
    {
        return ($name === 'title' || $templateField->searchable()) && $templateField->field->fieldType() instanceof ProvidesSearchText;
    }

    /**
     * Text as folded words: markup removed, lowercase, accents stripped (when intl is installed).
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        return array_map(self::fold(...), self::tokens($text));
    }

    /**
     * What an index row holds: each folded word, followed by its folded reduced form when it
     * differs, each term once.
     *
     * @return list<string>
     */
    public static function terms(string $text, ?WordReducer $reducer): array
    {
        $terms = [];

        foreach (self::tokens($text) as $token) {
            $terms[] = self::fold($token);
            $reduced = self::reduced($token, $reducer);

            if ($reduced !== null) {
                $terms[] = $reduced;
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * A query's words: each matches its own beginning, or its reduced form exactly.
     *
     * @return list<SearchWord>
     */
    public static function query(string $text, ?WordReducer $reducer): array
    {
        $words = [];

        foreach (self::tokens($text) as $token) {
            $word = new SearchWord(self::fold($token), self::reduced($token, $reducer));
            $words[$word->prefix.' '.$word->reduced] = $word;
        }

        return array_values($words);
    }

    /**
     * Text as lowercase words with their accents, markup removed.
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (class_exists(Normalizer::class)) {
            $composed = Normalizer::normalize($text, Normalizer::FORM_C);
            $text = is_string($composed) ? $composed : $text;
        }

        return preg_split('/[^\p{L}\p{N}\p{Mn}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** A word without its accents (when intl is installed). */
    public static function fold(string $word): string
    {
        if (! class_exists(Normalizer::class)) {
            return $word;
        }

        $decomposed = Normalizer::normalize($word, Normalizer::FORM_D);

        return preg_replace('/\p{Mn}+/u', '', is_string($decomposed) ? $decomposed : $word) ?? $word;
    }

    /** The folded reduced form of a word, or null when it has none or it is the word itself. */
    private static function reduced(string $token, ?WordReducer $reducer): ?string
    {
        if ($reducer === null || mb_strlen($token) < EndingRules::MIN_LENGTH) {
            return null;
        }

        $reduced = self::fold($reducer->reduce($token));

        return $reduced === self::fold($token) || preg_match('/^[\p{L}\p{N}]+$/uD', $reduced) !== 1 ? null : $reduced;
    }
}
