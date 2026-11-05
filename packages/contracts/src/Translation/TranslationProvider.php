<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Translation;

/**
 * Machine translation behind one interface: a model through laravel/ai, DeepL, a local model.
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
     * @param  array<string, mixed>  $options  what a provider should honour when it can:
     *                                         provider_code (string): the target's own code for this provider;
     *                                         glossary (array<string, string>): source term => the term to use in the target;
     *                                         formality ("more" | "less"): formal or informal address;
     *                                         context (string): what the site is about, for word choices
     * @return array<string, string> the same keys, translated
     */
    public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array;
}
