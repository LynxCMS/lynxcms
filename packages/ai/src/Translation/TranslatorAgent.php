<?php

declare(strict_types=1);

namespace LynxCms\Ai\Translation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Translates a batch of field values with structured output: one property per field.
 */
class TranslatorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $keys
     */
    public function __construct(private readonly array $keys = []) {}

    public function instructions(): Stringable|string
    {
        return 'You are a professional translator for website content. Translate every value faithfully and naturally for native readers. '
            .'Keep HTML tags, attributes and Markdown syntax exactly as they are, translating only the human-readable text. '
            .'Never translate text inside {page:…} or {{ … }} placeholders, product names, brand names or acronyms such as API, ISO or GDPR. '
            .'Return every key you received.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translations' => $schema->object(fn (JsonSchema $s): array => array_combine(
                $this->keys,
                array_map(fn (): mixed => $s->string()->required(), $this->keys),
            ))->required(),
        ];
    }
}
