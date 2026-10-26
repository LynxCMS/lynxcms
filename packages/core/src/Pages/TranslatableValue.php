<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use LynxCms\Core\Fields\FormatterChain;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * The value of a translatable field. It resolves through the current language's fallback chain
 * each time it is read, field by field (ADR-0005), and knows which language it came from.
 *
 * In Blade, {{ $page->title }} prints the value formatted for output (escaped plain text, HTML
 * as stored, then the field's formatters, ADR-0031); {!! $page->title !!} and value() give the
 * stored string.
 *
 * @api
 */
final readonly class TranslatableValue implements FieldValue
{
    /**
     * @param  array<int, mixed>  $values  language id => value (only languages with a stored row)
     */
    public function __construct(
        private Field $field,
        private array $values,
        private LanguageContext $context,
        private LanguageRegistry $languages,
    ) {}

    public function value(): mixed
    {
        $language = $this->resolvedLanguage();

        return $language === null ? null : $this->values[$language->id];
    }

    /** The language the visible value comes from, or null when no language in the chain has one. */
    public function resolvedLanguage(): ?Language
    {
        $chain = $this->field->fallbackMode() === 'none'
            ? [$this->context->current()]
            : $this->context->chain();

        foreach ($chain as $language) {
            if (array_key_exists($language->id, $this->values)) {
                return $language;
            }
        }

        return null;
    }

    public function isFallback(): bool
    {
        $language = $this->resolvedLanguage();

        return $language !== null && $language->id !== $this->context->current()->id;
    }

    /** Strict value in one language, without fallback. */
    public function in(Language|string $language): mixed
    {
        return $this->values[$this->languages->get($language)->id] ?? null;
    }

    public function has(Language|string $language): bool
    {
        return array_key_exists($this->languages->get($language)->id, $this->values);
    }

    /** True when no language in the current chain has a value, so templates read the field as null (ADR-0087). */
    public function isEmpty(): bool
    {
        $value = $this->value();

        return $value === null || $value === '';
    }

    /** True when no language holds a value, not even one outside the current chain. */
    public function isBlank(): bool
    {
        return array_all($this->values, fn (mixed $value): bool => $value === null || $value === '');
    }

    /** Code of the language actually shown, for lang="" attributes. */
    public function lang(): ?string
    {
        return $this->resolvedLanguage()?->appLocale();
    }

    public function field(): Field
    {
        return $this->field;
    }

    /**
     * @return array<int, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function toHtml(): string
    {
        return app(FormatterChain::class)->render($this->field, $this->value(), $this->resolvedLanguage());
    }

    public function __toString(): string
    {
        return Value::string($this->value());
    }

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
