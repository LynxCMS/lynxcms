<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

/**
 * The rel rule shared by every link editors make (rich text, menus): they choose among CHOICES,
 * and a link that opens a new window always gets noopener, so the opened page cannot reach back
 * through window.opener. noreferrer is a choice, not a default, so sites linked to still see
 * where their visitors came from.
 *
 * @api
 */
final class LinkRel
{
    /** Relations editors may give a link: for search engines, or to hide where a visitor came from. */
    public const array CHOICES = ['nofollow', 'sponsored', 'ugc', 'noreferrer'];

    /** Added to links that open a new window. */
    public const string NOOPENER = 'noopener';

    /**
     * The chosen relations among CHOICES, once each, in the order given.
     *
     * @param  string|list<string>  $rel  a rel attribute ("nofollow sponsored") or a list
     * @return list<string>
     */
    public static function chosen(string|array $rel): array
    {
        $tokens = is_string($rel) ? (preg_split('/\s+/', trim($rel), -1, PREG_SPLIT_NO_EMPTY) ?: []) : $rel;

        return array_values(array_unique(array_intersect(array_map(strtolower(...), $tokens), self::CHOICES)));
    }

    /**
     * A link's rel: the chosen relations, plus noopener when it opens a new window; null when empty.
     *
     * @param  string|list<string>  $rel
     */
    public static function for(string|array $rel, bool $opensNewWindow): ?string
    {
        $tokens = [...self::chosen($rel), ...($opensNewWindow ? [self::NOOPENER] : [])];

        return $tokens === [] ? null : implode(' ', $tokens);
    }
}
