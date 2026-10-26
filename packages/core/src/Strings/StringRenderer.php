<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Support\LocalizedText;
use Stringable;

/**
 * What @string prints (ADR-0047): the editors' text in the current language or along its fallback
 * chain, else the template's code text, with values put in its placeholders (":room", as in
 * Laravel's translations). Escaped, values included; "markdown" strings render Markdown with HTML
 * in the text escaped. Site strings live on the home page.
 *
 * @internal
 */
final readonly class StringRenderer
{
    public function __construct(private StringStore $store, private LanguageContext $context, private RootPage $root) {}

    /**
     * What @string compiles to: $defaultScope is the scope of the view the call is in, used when
     * the call names none (ADR-0051).
     *
     * @param  string|array<string, string>  $default
     * @param  array<string, string|int|float|Stringable>  $replace
     */
    public function renderIn(string $defaultScope, ?Page $page, string $key, string|array $default, ?string $label = null, ?string $scope = null, string $format = 'plain', array $replace = []): string
    {
        return $this->render($page, $key, $default, $label, $scope ?? $defaultScope, $format, $replace);
    }

    /**
     * @param  string|array<string, string>  $default
     * @param  array<string, string|int|float|Stringable>  $replace
     */
    public function render(?Page $page, string $key, string|array $default, ?string $label = null, string $scope = StringDefinition::PAGE, string $format = 'plain', array $replace = []): string
    {
        $text = self::fill($this->text($page, $key, $default, $scope), $replace);

        return $format === 'markdown'
            ? trim((string) new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false])->convert($text))
            : e($text);
    }

    /**
     * @param  string|array<string, string>  $default
     */
    public function text(?Page $page, string $key, string|array $default, string $scope = StringDefinition::PAGE): string
    {
        $owner = $scope === StringDefinition::SITE ? $this->root->id() : $page?->contentId();
        $edits = $owner === null ? [] : ($this->store->forPage($owner)[$key] ?? []);

        foreach ($this->context->chainIds() as $languageId) {
            if (isset($edits[$languageId])) {
                return $edits[$languageId];
            }
        }

        return LocalizedText::from($default)->in();
    }

    /**
     * The text with each ":name" placeholder replaced by its value, as Laravel's translator does:
     * ":Name" capitalises the value and ":NAME" upper-cases it. strtr() tries longer names first,
     * so ":room" does not eat the start of ":rooms".
     *
     * @param  array<string, string|int|float|Stringable>  $replace
     */
    public static function fill(string $text, array $replace): string
    {
        $pairs = [];

        foreach ($replace as $name => $value) {
            $value = (string) $value;
            $pairs[':'.ucfirst($name)] = ucfirst($value);
            $pairs[':'.mb_strtoupper($name)] = mb_strtoupper($value);
            $pairs[':'.$name] = $value;
        }

        return strtr($text, $pairs);
    }
}
