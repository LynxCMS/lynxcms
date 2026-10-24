<?php

declare(strict_types=1);

namespace LynxCms\Seo\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\SeoData;

/**
 * A page's SEO as search engines and social networks get it: title, description, canonical URL,
 * robots rule, share image and language links, in one language.
 */
final class InspectSeoTool extends AgentReadTool
{
    public static function name(): string
    {
        return 'seo_inspect';
    }

    public static function description(): string
    {
        return 'Read a page\'s SEO as search engines see it: the final title (after the site\'s title pattern), description (own or taken from content), canonical URL, robots rule, share image and hreflang links. Use it to check a page before or after editing its seo_* fields.';
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('The page id or path, e.g. "42" or "/courses/risk/".')->required(),
            'language' => $schema->string()->description('Language code; the default language when left out.'),
        ];
    }

    public static function permission(): string
    {
        return 'pages.view';
    }

    public function handle(array $arguments): mixed
    {
        $reference = Value::string($arguments['page'] ?? '');
        $page = lynx()->pages->get(ctype_digit($reference) ? (int) $reference : $reference);
        $language = Value::string($arguments['language'] ?? '') ?: (string) lynx()->languages->default()->code;

        // A page outside the user's pages.view scope reads as missing, as get_page answers.
        if ($page instanceof Page && ! app(Authorizer::class)->allows(app(ActionRunner::class)->actor(), 'pages.view', $page)) {
            $page = null;
        }

        if (! $page instanceof Page || lynx()->languages->find($language) === null) {
            return ['error' => $page instanceof Page ? "There is no language [{$language}]." : 'There is no such page.'];
        }

        return lynx()->language($language, fn (): SeoData => lynx()->seo($page));
    }
}
