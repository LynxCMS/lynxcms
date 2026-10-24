<?php

declare(strict_types=1);

namespace LynxCms\Forms\Notifications;

/**
 * Text a visitor typed, made safe to put in a Markdown mail: Markdown's punctuation is escaped,
 * so an answer cannot add links, images or formatting to an email the site sends its staff.
 * HTML needs nothing here, since mail lines are already HTML-escaped.
 *
 * @internal
 */
final class PlainMarkdown
{
    /** Markdown punctuation, without < > & which the HTML escaping already covers. */
    private const string SPECIAL = '/([\\\\`*_{}\[\]()#+\-.!|~])/';

    public static function escape(string $text): string
    {
        return (string) preg_replace(self::SPECIAL, '\\\\$1', $text);
    }
}
