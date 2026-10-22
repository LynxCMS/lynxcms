<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Translation;

/**
 * Machine translation behind one interface: an LLM through laravel/ai in the MVP, DeepL later.
 *
 * @api
 */
interface TranslationProvider
{
    /** Short machine name stored with each translated value, e.g. "laravel-ai" or "deepl". */
    public function name(): string;

    /**
     * Translate several texts in one call.
     *
     * @param  array<string, string>  $texts  key => text
     * @param  array<string, string>  $formats  key => "plain" | "html" | "markdown"
     * @param  array<string, mixed>  $options  e.g. glossary, tone
     * @return array<string, string> the same keys, translated
     */
    public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array;
}
