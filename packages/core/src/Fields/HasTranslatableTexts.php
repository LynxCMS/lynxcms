<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Models\Language;

/**
 * A field type whose shared value holds texts per language, such as a form question's options
 * with a label in each language (ADR-0105). Machine translation reads the texts missing in a
 * language and writes them back without touching the rest of the value.
 *
 * @api
 */
interface HasTranslatableTexts
{
    /**
     * The texts with no translation in $to yet, by a key of the type's own choosing.
     *
     * @return array<string, string>
     */
    public function untranslatedTexts(mixed $value, Language $from, Language $to): array;

    /**
     * The value with the translated texts put in.
     *
     * @param  array<string, string>  $texts  keyed as untranslatedTexts() keys them
     */
    public function withTranslatedTexts(mixed $value, Language $to, array $texts): mixed;
}
