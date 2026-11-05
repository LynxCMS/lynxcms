<?php

declare(strict_types=1);

namespace LynxCms\Ai\Translation;

use Laravel\Ai\Responses\StructuredAgentResponse;
use LynxCms\Ai\LynxAi;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Support\Value;
use UnexpectedValueException;

/**
 * Machine translation through the Laravel AI SDK, used by pages.translate (ADR-0016, ADR-0020).
 * DeepL can replace it later behind the same interface.
 */
final class LaravelAiTranslationProvider implements TranslationProvider
{
    public function name(): string
    {
        return 'laravel-ai';
    }

    public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
    {
        if ($texts === []) {
            return [];
        }

        $payload = [];

        foreach ($texts as $key => $text) {
            $payload[$key] = ['format' => $formats[$key] ?? 'plain', 'text' => $text];
        }

        $response = new TranslatorAgent(array_keys($texts))->prompt(
            "Translate from {$from} to {$to}.".$this->guidance($options)."\nValues:\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            provider: LynxAi::provider(),
            model: Value::nullableString(config('lynx.ai.translation_model') ?? config('lynx.ai.model')),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new UnexpectedValueException('The translator agent did not return structured output.');
        }

        $translations = Value::map($response->toArray()['translations'] ?? []);

        return array_intersect_key(array_map(Value::string(...), $translations), $texts);
    }

    /**
     * The options the model can honour, as sentences: the glossary's terms, the tone, the site.
     *
     * @param  array<string, mixed>  $options
     */
    private function guidance(array $options): string
    {
        $lines = [];
        $glossary = Value::map($options['glossary'] ?? []);

        if ($glossary !== []) {
            $terms = array_map(fn (string $source, mixed $target): string => '"'.$source.'" as "'.Value::string($target).'"', array_keys($glossary), $glossary);
            $lines[] = 'Always translate '.implode(', ', $terms).'.';
        }

        $lines[] = match ($options['formality'] ?? null) {
            'more' => 'Address the reader formally.',
            'less' => 'Address the reader in an informal, friendly tone.',
            default => null,
        };
        $context = Value::nullableString($options['context'] ?? null);
        $lines[] = $context === null ? null : "The site: {$context}";

        $lines = array_filter($lines);

        return $lines === [] ? '' : "\n".implode("\n", $lines);
    }
}
