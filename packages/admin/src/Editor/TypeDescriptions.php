<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Support\Value;

/**
 * Field type descriptions as the field form shows them. Descriptions also guide MCP and the AI
 * agent, so they mention languages; a site with one language leaves those sentences out.
 *
 * @internal
 */
final class TypeDescriptions
{
    /** A sentence about languages: "Shared across languages.", "Can be translatable." */
    private const string LANGUAGE_SENTENCE = '/\b(languages?|translatable|default-language)\b/i';

    /**
     * @param  array<string, array<string, mixed>>  $types  lynx()->fields->types()
     * @return array<string, array<string, mixed>>
     */
    public static function forSite(array $types): array
    {
        if (lynx()->languages->isMultilingual()) {
            return $types;
        }

        return array_map(fn (array $type): array => ['description' => self::withoutLanguages(Value::string($type['description'] ?? null))] + $type, $types);
    }

    /** Sentences end with a period followed by a capital letter, so "e.g. a title" stays whole. */
    private static function withoutLanguages(string $description): string
    {
        $sentences = preg_split('/(?<=\.)\s+(?=[A-Z])/', $description) ?: [];

        return implode(' ', array_filter($sentences, fn (string $sentence): bool => preg_match(self::LANGUAGE_SENTENCE, $sentence) !== 1));
    }
}
