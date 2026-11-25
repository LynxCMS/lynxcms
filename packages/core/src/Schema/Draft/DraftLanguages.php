<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use Illuminate\Support\Str;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\Value;

/**
 * The languages section of a draft (ADR-0116), as the items schema sync applies. Each entry is a
 * language by code: a name, or a map of name, default, label, locale, prefix, fallback and active.
 * The entry marked default is the site's default language, whatever its code is now, so a draft
 * can rename it; another language can't become the default. Settings an entry leaves out keep the
 * site's values, and languages the draft leaves out stay.
 *
 * @phpstan-import-type LanguageItem from SchemaSync
 *
 * @internal
 */
final readonly class DraftLanguages
{
    public const array KEYS = ['name', 'default', 'label', 'locale', 'prefix', 'fallback', 'active'];

    /** The codes languages.create accepts. */
    private const string CODE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,31}$/D';

    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @return list<LanguageItem>
     *
     * @throws DraftError
     */
    public function compile(mixed $section): array
    {
        $entries = [];

        foreach (Value::map($section ?? []) as $code => $entry) {
            $entries[(string) $code] = $this->entry((string) $code, $entry);
        }

        $default = $this->defaultCode($entries);
        $siteDefault = (string) $this->languages->default()->code;
        $renamed = $default !== null && $default !== $siteDefault ? $default : null;

        if ($renamed !== null && isset($entries[$siteDefault])) {
            throw new DraftError("Language [{$siteDefault}] is the default language, which the draft renames to [{$renamed}]. Leave [{$siteDefault}] out.");
        }

        $items = [];

        foreach ($entries as $code => $entry) {
            $items[] = $this->item($code, $entry, $code === $default, $renamed);
        }

        $this->assertFallbacks($items);

        return $items;
    }

    /**
     * A step for each language the draft creates or changes.
     *
     * @param  list<LanguageItem>  $items
     * @return list<array{action: string, kind: string, name: string}>
     */
    public function steps(array $items): array
    {
        $steps = [];
        $default = array_first(array_filter($items, fn (array $item): bool => $item['default']));
        $renamed = $default !== null && $default['code'] !== $this->languages->default()->code ? $default['code'] : null;

        foreach ($items as $item) {
            $existing = $this->languages->find($item['uuid']);

            if (! $existing instanceof Language) {
                $steps[] = ['action' => DraftPlan::CREATE, 'kind' => 'language', 'name' => $item['code']];
            } elseif ($this->item($item['code'], [], $item['default'], $renamed) !== $item) {
                $steps[] = ['action' => DraftPlan::UPDATE, 'kind' => 'language', 'name' => $existing->code === $item['code'] ? $item['code'] : "{$existing->code}, code {$item['code']}"];
            }
        }

        return $steps;
    }

    /** @return array<string, mixed> */
    private function entry(string $code, mixed $entry): array
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new DraftError("Language [{$code}]: a code has lowercase letters, digits, \"-\" and \"_\", such as pt or pt-br.");
        }

        $entry = is_string($entry) ? ['name' => $entry] : Value::map($entry ?? []);

        foreach (array_keys($entry) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                throw new DraftError("Language [{$code}] has no setting [{$key}].".DraftError::suggestion((string) $key, self::KEYS).' Its settings are '.implode(', ', self::KEYS).'.');
            }
        }

        return $entry;
    }

    /**
     * The code of the entry that is the site's default language: the one marked default, or the
     * one with the default's code. Null when the draft leaves the default language out.
     *
     * @param  array<string, array<string, mixed>>  $entries
     */
    private function defaultCode(array $entries): ?string
    {
        $marked = array_keys(array_filter($entries, fn (array $entry): bool => ($entry['default'] ?? false) === true));

        if (count($marked) > 1) {
            throw new DraftError('Languages ['.implode(', ', $marked).'] are marked default. A site has one default language.');
        }

        $siteDefault = $this->languages->default();
        $code = $marked[0] ?? null;

        if ($code === null) {
            return isset($entries[$siteDefault->code]) ? (string) $siteDefault->code : null;
        }

        $existing = $this->languages->find($code);

        if ($existing instanceof Language && $existing->id !== $siteDefault->id) {
            throw new DraftError("Language [{$code}] is not the default language, which is [{$siteDefault->code}]. A draft can rename the default language, and can't make another one the default.");
        }

        return $code;
    }

    /**
     * The entry as an item, with the site's values for what it leaves out. $renamedDefault is the
     * default language's new code when the draft renames it.
     *
     * @param  array<string, mixed>  $entry
     * @return LanguageItem
     */
    private function item(string $code, array $entry, bool $isDefault, ?string $renamedDefault): array
    {
        $existing = $isDefault ? $this->languages->default() : $this->languages->find($code);

        if (! $existing instanceof Language) {
            return $this->newItem($code, $entry);
        }

        if ($isDefault && isset($entry['fallback'])) {
            throw new DraftError("Language [{$code}] is the default language, which has no fallback.");
        }

        $fallback = $existing->fallback_id === null ? null : $this->languages->find($existing->fallback_id)?->code;

        // A fallback to the default language follows its new code.
        if ($renamedDefault !== null && $fallback === $this->languages->default()->code) {
            $fallback = $renamedDefault;
        }

        return [
            'uuid' => (string) $existing->uuid,
            'code' => $code,
            'name' => Value::nullableString($entry['name'] ?? null) ?? (string) $existing->name,
            'label' => array_key_exists('label', $entry) ? Value::nullableString($entry['label']) : $existing->label,
            'locale' => array_key_exists('locale', $entry) ? Value::nullableString($entry['locale']) : $existing->locale,
            'fallback' => array_key_exists('fallback', $entry) ? Value::nullableString($entry['fallback']) : $fallback,
            'prefix' => isset($entry['prefix']) ? Value::string($entry['prefix']) : (string) $existing->url_prefix,
            'active' => isset($entry['active']) ? Value::bool($entry['active']) : (bool) $existing->is_active,
            'default' => $isDefault,
            'provider_codes' => $existing->provider_codes,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return LanguageItem
     */
    private function newItem(string $code, array $entry): array
    {
        $name = Value::nullableString($entry['name'] ?? null) ?? throw new DraftError("Language [{$code}] is new, so it needs a name: {$code}: Name, or {$code}: { name: Name }.");

        return [
            'uuid' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'label' => Value::nullableString($entry['label'] ?? null),
            'locale' => Value::nullableString($entry['locale'] ?? null),
            'fallback' => Value::nullableString($entry['fallback'] ?? null),
            'prefix' => isset($entry['prefix']) ? Value::string($entry['prefix']) : $code,
            'active' => isset($entry['active']) ? Value::bool($entry['active']) : true,
            'default' => false,
            'provider_codes' => null,
        ];
    }

    /** @param  list<LanguageItem>  $items */
    private function assertFallbacks(array $items): void
    {
        $known = [...array_column($items, 'code'), ...$this->languages->all()->map(fn (Language $l): string => (string) $l->code)->values()->all()];

        foreach ($items as $item) {
            if ($item['fallback'] !== null && ! in_array($item['fallback'], $known, true)) {
                throw new DraftError("Language [{$item['code']}] falls back to [{$item['fallback']}], which neither the draft nor the site has.".DraftError::suggestion($item['fallback'], array_values(array_unique($known))));
            }
        }
    }
}
