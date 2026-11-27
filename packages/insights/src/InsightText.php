<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use LynxCms\Core\Support\Value;
use Stringable;

/**
 * Wording of an insight, kept as a translation key and its replacements, so a report written in
 * one language reads in each editor's own. With a count, the key holds plural forms and
 * trans_choice picks one. Plain text works as a key too, and shows as it is.
 *
 * @api
 */
final readonly class InsightText implements Stringable
{
    /**
     * @param  array<string, string|int|float>  $replace
     */
    public function __construct(
        public string $key,
        public array $replace = [],
        public ?int $count = null,
    ) {}

    public static function from(self|string $text): self
    {
        return $text instanceof self ? $text : new self($text);
    }

    /**
     * @param  array<mixed>  $stored  what toArray() returned
     */
    public static function fromArray(array $stored): self
    {
        $replace = [];

        foreach (Value::array($stored['replace'] ?? []) as $name => $value) {
            $replace[(string) $name] = is_int($value) || is_float($value) ? $value : Value::string($value);
        }

        return new self(Value::string($stored['key'] ?? ''), $replace, isset($stored['count']) ? Value::int($stored['count']) : null);
    }

    /** The wording in the locale, by default the current one. */
    public function translate(?string $locale = null): string
    {
        return $this->count === null
            ? Value::string(__($this->key, $this->replace, $locale))
            : trans_choice($this->key, $this->count, $this->replace, $locale);
    }

    /**
     * @return array{key: string, replace: array<string, string|int|float>, count: int|null}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'replace' => $this->replace, 'count' => $this->count];
    }

    public function __toString(): string
    {
        return $this->translate();
    }
}
