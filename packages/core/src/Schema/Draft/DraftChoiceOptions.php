<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Str;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Value;

/**
 * A choice field's options in a draft (ADR-0116). They are written as labels ("Small"), as
 * {value, label} rows, or as a map of default language labels to their translations
 * ({Small: {pt: Pequeno}}), where each value is the label slugged. A draft that gives an option
 * the site has a label in one language changes that language only, so translations made in the
 * admin survive a rebuild.
 *
 * @internal
 */
final readonly class DraftChoiceOptions
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @return list<mixed> the options as rows, for ChoiceOptions to check
     */
    public function rows(mixed $options): array
    {
        if (! is_array($options) || array_is_list($options)) {
            return array_map(fn (mixed $option): mixed => is_string($option) ? ['value' => Str::slug($option), 'label' => $option] : $option, Value::list($options));
        }

        $rows = [];

        foreach ($options as $label => $translations) {
            $label = (string) $label;
            $translations = Value::map($translations ?? []);
            $rows[] = ['value' => Str::slug($label), 'label' => $translations === [] ? $label : [$this->defaultCode() => $label, ...$translations]];
        }

        return $rows;
    }

    /**
     * The draft's options, each keeping the site's translations when the draft gives its label in
     * one language only.
     *
     * @param  list<mixed>  $draft
     * @param  list<mixed>  $site
     * @return list<mixed>
     */
    public function keepTranslations(array $draft, array $site): array
    {
        $labels = array_column(array_map(Value::map(...), $site), 'label', 'value');

        return array_map(function (mixed $option) use ($labels): mixed {
            $option = Value::map($option);
            $existing = $labels[Value::string($option['value'] ?? '')] ?? null;

            return is_string($option['label'] ?? null) && is_array($existing)
                ? [...$option, 'label' => [...$existing, $this->defaultCode() => $option['label']]]
                : $option;
        }, $draft);
    }

    /**
     * The options as a map of default language labels to their translations (null for none), or
     * null when the map can't say them: a value that isn't its label slugged, or extra keys.
     *
     * @param  list<mixed>  $options
     * @return array<string, array<string, string>|null>|null
     */
    public function map(array $options): ?array
    {
        $map = [];
        $code = $this->defaultCode();

        foreach ($options as $option) {
            $option = Value::map($option);
            $label = $option['label'] ?? null;
            $default = is_array($label) ? $label[$code] ?? null : $label;

            if (! is_string($default) || array_keys($option) !== ['value', 'label'] || $option['value'] !== Str::slug($default)) {
                return null;
            }

            $translations = is_array($label) ? array_map(Value::string(...), Value::map(array_diff_key($label, [$code => true]))) : [];
            $map[$default] = $translations === [] ? null : $translations;
        }

        return count($map) === count($options) ? $map : null;
    }

    private function defaultCode(): string
    {
        return (string) $this->languages->default()->code;
    }
}
