<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Fields;

/**
 * A text formatter (ADR-0031): attached to text fields in an ordered list, run when a template
 * outputs the value, never on the stored value. It receives HTML (plain text arrives escaped) and
 * returns HTML; a chain that produces HTML is cleaned afterwards.
 *
 * @api
 */
interface Formatter
{
    /** The name fields list it by, e.g. "smartypants". */
    public static function name(): string;

    /** One sentence for site builders and agents. */
    public static function description(): string;

    /** Whether it can format values of this field type ("text", "textarea", or a module's type). */
    public function appliesTo(string $fieldType): bool;

    /** Whether it may add markup; such chains end with the HTML cleaner. */
    public function producesHtml(): bool;

    public function format(string $html, FormatContext $context): string;
}
