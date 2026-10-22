<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

use Closure;
use JsonSerializable;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use Stringable;

/**
 * Schema text editors read, such as a field's label, description or notes (ADR-0043): one string
 * for every language, or a map of language codes to text. Reading picks the language (by default
 * the current one), then its fallback chain, then the default language, then any text there is.
 *
 * @api
 */
final readonly class LocalizedText implements JsonSerializable, Stringable
{
    /**
     * @param  string|array<string, string>  $value
     */
    private function __construct(private string|array $value) {}

    /** A string, a map of language codes to text, or that map as JSON (as label columns store it). */
    public static function from(mixed $raw): self
    {
        if ($raw instanceof self) {
            return $raw;
        }

        if (is_array($raw)) {
            $map = [];

            foreach ($raw as $code => $text) {
                if (is_scalar($text) && trim((string) $text) !== '') {
                    $map[(string) $code] = (string) $text;
                }
            }

            return new self($map);
        }

        if (is_string($raw) && str_starts_with(ltrim($raw), '{')) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return self::from($decoded);
            }
        }

        return new self(is_scalar($raw) ? (string) $raw : '');
    }

    public function in(Language|string|null $language = null): string
    {
        if (is_string($this->value)) {
            return $this->value;
        }

        foreach ($this->codes($language) as $code) {
            if (isset($this->value[$code])) {
                return $this->value[$code];
            }
        }

        return array_first($this->value) ?? '';
    }

    public function isLocalized(): bool
    {
        return is_array($this->value);
    }

    public function isEmpty(): bool
    {
        return $this->value === '' || $this->value === [];
    }

    /**
     * What schema files, summaries and actions use: the string, or the map.
     *
     * @return string|array<string, string>
     */
    public function toStorage(): string|array
    {
        return $this->value;
    }

    /** What a label column stores: the string, or the map as JSON. */
    public function toColumn(): string
    {
        return is_string($this->value) ? $this->value : json_encode($this->value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function renameLanguage(string $from, string $to): self
    {
        if (is_string($this->value) || ! isset($this->value[$from])) {
            return $this;
        }

        $map = [];

        foreach ($this->value as $code => $text) {
            $map[$code === $from ? $to : $code] = $text;
        }

        return new self($map);
    }

    /**
     * Validation rules: a string, or a map whose keys are existing language codes.
     *
     * @return list<mixed>
     */
    public static function rules(int $max = 255): array
    {
        return [function (string $attribute, mixed $value, Closure $fail) use ($max): void {
            if (is_string($value)) {
                if (mb_strlen($value) > $max) {
                    $fail("The {$attribute} may not be longer than {$max} characters.");
                }

                return;
            }

            if (! is_array($value)) {
                $fail("The {$attribute} must be text, or text per language code.");

                return;
            }

            foreach ($value as $code => $text) {
                if (! app(LanguageRegistry::class)->find((string) $code) instanceof Language) {
                    $fail("The {$attribute} uses the language [{$code}], which does not exist.");
                } elseif (! is_string($text) || mb_strlen($text) > $max) {
                    $fail("The {$attribute} in [{$code}] must be text of at most {$max} characters.");
                }
            }
        }];
    }

    public function __toString(): string
    {
        return $this->in();
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * Language codes to try, in order.
     *
     * @return list<string>
     */
    private function codes(Language|string|null $language): array
    {
        $languages = app(LanguageRegistry::class);

        if (! $languages->hasDefault()) {
            return [];
        }

        $chain = $language === null
            ? app(LanguageContext::class)->chain()
            : ($languages->find($language) instanceof Language ? $languages->chain($language) : []);
        $codes = array_map(fn (Language $l): string => (string) $l->code, $chain);
        $codes[] = (string) $languages->default()->code;

        return array_values(array_unique($codes));
    }
}
