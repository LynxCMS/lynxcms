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
            "Translate from {$from} to {$to}. Values:\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            provider: LynxAi::provider(),
            model: Value::nullableString(config('lynx.ai.translation_model') ?? config('lynx.ai.model')),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new UnexpectedValueException('The translator agent did not return structured output.');
        }

        $translations = Value::map($response->toArray()['translations'] ?? []);

        return array_intersect_key(array_map(Value::string(...), $translations), $texts);
    }
}
