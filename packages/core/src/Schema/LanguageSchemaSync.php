<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Closure;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Languages\UpdateLanguage;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;

/**
 * The languages part of schema sync (SchemaSync): creates and updates languages from their files,
 * the default language first, and sets fallbacks once every language exists.
 *
 * @phpstan-import-type LanguageItem from SchemaSync
 *
 * @internal
 */
final readonly class LanguageSchemaSync
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @param  list<LanguageItem>  $items
     * @param  Closure(class-string<Action>, array<string, mixed>): void  $run  runs an action, recording its changes or error
     */
    public function apply(array $items, SchemaSyncResult $result, Closure $run): void
    {
        // The default language first (on an empty site the first language created becomes the
        // default), then languages without a fallback; fallbacks are set in a second pass.
        $rank = fn (array $l): int => $l['default'] ? 0 : ($l['fallback'] === null ? 1 : 2);
        usort($items, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        foreach ($items as $item) {
            $this->applyLanguage($item, $result, $run);
        }

        $this->applyFallbacks($items, $run);
    }

    /**
     * @param  LanguageItem  $item
     * @param  Closure(class-string<Action>, array<string, mixed>): void  $run
     */
    private function applyLanguage(array $item, SchemaSyncResult $result, Closure $run): void
    {
        $existing = $this->languages->find($item['uuid']) ?? $this->languages->find($item['code']);

        if ($existing === null) {
            $run(CreateLanguage::class, array_intersect_key($item, array_flip(['uuid', 'code', 'name', 'label', 'locale', 'prefix', 'active', 'provider_codes'])));

            return;
        }

        $changes = $this->languageChanges($existing, $item);

        if ($changes !== []) {
            $run(UpdateLanguage::class, ['language' => $existing->code] + $changes);
        }

        if ($item['default'] && $existing->id !== $this->languages->default()->id) {
            $result->errors[] = "Language {$item['code']} is the default in the files; switching the default language is not supported yet.";
        }
    }

    /**
     * What differs between the language and its file, as languages.update takes it.
     *
     * @param  LanguageItem  $item
     * @return array<string, mixed>
     */
    private function languageChanges(Language $existing, array $item): array
    {
        $changes = array_filter([
            'code' => $existing->code !== $item['code'] ? $item['code'] : null,
            'name' => $existing->name !== $item['name'] ? $item['name'] : null,
            'label' => $existing->label !== $item['label'] ? ($item['label'] ?? '') : null,
            'locale' => $existing->locale !== $item['locale'] ? ($item['locale'] ?? '') : null,
            'prefix' => $existing->url_prefix !== $item['prefix'] ? $item['prefix'] : null,
            'active' => $existing->is_active !== $item['active'] ? $item['active'] : null,
        ], fn (mixed $v): bool => $v !== null);

        if (($changes['locale'] ?? null) === '') {
            $changes['locale'] = null;
        }

        return $changes;
    }

    /**
     * Fallbacks in a second pass, once every language exists.
     *
     * @param  list<LanguageItem>  $items
     * @param  Closure(class-string<Action>, array<string, mixed>): void  $run
     */
    private function applyFallbacks(array $items, Closure $run): void
    {
        foreach ($items as $item) {
            $language = $this->languages->find($item['uuid']);
            $current = $language?->fallback_id === null ? null : $this->languages->find($language->fallback_id)?->code;

            if ($language !== null && $current !== $item['fallback'] && ! $item['default']) {
                $run(UpdateLanguage::class, ['language' => $language->code, 'fallback' => $item['fallback']]);
            }
        }
    }
}
