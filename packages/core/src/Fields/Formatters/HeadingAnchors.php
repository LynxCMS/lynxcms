<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use Illuminate\Support\Str;
use LynxCms\Contracts\Fields\FormatContext;

/**
 * Gives each h2, h3 and h4 an id made from its text ("Opening hours" gets id="opening-hours"), so
 * links can point at a section: /about/#opening-hours. Ids are unique within the text. The admin's
 * link dialog lists the same ids with anchors().
 */
final class HeadingAnchors extends TextFormatter
{
    private const string HEADING = '#<(h[2-4])((?:\s[^>]*)?)>(.*?)</\1>#is';

    public static function name(): string
    {
        return 'heading_anchors';
    }

    public static function description(): string
    {
        return 'Gives headings an id made from their text, so links can point at a section.';
    }

    public function format(string $html, FormatContext $context): string
    {
        $taken = [];

        return (string) preg_replace_callback(self::HEADING, function (array $match) use (&$taken): string {
            if (preg_match('/\sid\s*=/i', $match[2]) === 1) {
                return $match[0];
            }

            return '<'.$match[1].$match[2].' id="'.self::uniqueId($match[3], $taken).'">'.$match[3].'</'.$match[1].'>';
        }, $html);
    }

    /**
     * The ids format() gives the headings of $html, with their text.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function anchors(string $html): array
    {
        $taken = [];
        $anchors = [];
        preg_match_all(self::HEADING, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (preg_match('/\sid\s*=/i', $match[2]) !== 1) {
                $anchors[] = ['id' => self::uniqueId($match[3], $taken), 'label' => trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
            }
        }

        return $anchors;
    }

    /** @param  array<string, true>  $taken */
    private static function uniqueId(string $heading, array &$taken): string
    {
        $base = Str::slug(html_entity_decode(strip_tags($heading), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $base = $base === '' ? 'section' : $base;
        $id = $base;

        for ($number = 2; isset($taken[$id]); $number++) {
            $id = $base.'-'.$number;
        }

        $taken[$id] = true;

        return $id;
    }
}
