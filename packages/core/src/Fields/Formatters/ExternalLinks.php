<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use LynxCms\Contracts\Fields\FormatContext;
use LynxCms\Core\Fields\LinkRelSanitizer;
use LynxCms\Core\Support\Value;

/**
 * Links to other sites open in a new tab, with noopener added to their rel. Links that already
 * set a target are left alone.
 */
final class ExternalLinks extends TextFormatter
{
    public static function name(): string
    {
        return 'external_links';
    }

    public static function description(): string
    {
        return 'Links to other sites open in a new tab, with rel="noopener".';
    }

    public function format(string $html, FormatContext $context): string
    {
        $ownHost = parse_url(Value::string(config('app.url')), PHP_URL_HOST);

        return (string) preg_replace_callback('/<a\s([^>]*)>/i', function (array $match) use ($ownHost): string {
            $isExternal = preg_match('/href="(https?:\/\/[^"]+)"/i', $match[1], $href) === 1
                && parse_url(html_entity_decode($href[1]), PHP_URL_HOST) !== $ownHost;

            if (! $isExternal || preg_match('/\starget=/i', ' '.$match[1]) === 1) {
                return $match[0];
            }

            return '<a '.LinkRelSanitizer::withNoopener(rtrim($match[1]).' target="_blank"').'>';
        }, $html);
    }
}
