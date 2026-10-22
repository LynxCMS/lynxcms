<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use LynxCms\Cache\PageCacheSchema;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * The template rules in effect: those saved on the settings page, else lynx-cache.pages.templates.
 * A template named there uses its own rule (false turns it off), any other uses the "*" rule when
 * there is one, and is not cached when there is not.
 *
 * @internal
 */
final class TemplateRules
{
    private const string ANY = '*';

    /** Whether any template clears less than every page, which is when changes need looking at. */
    public function hasNarrowRules(): bool
    {
        return array_any(array_keys($this->all()), fn (string $template): bool => $this->for($template)?->clearsEverything() === false);
    }

    public function for(string $template): ?TemplateRule
    {
        $rules = $this->all();
        $rule = $rules[$template] ?? $rules[self::ANY] ?? null;

        if ($rule === null || $rule === false) {
            return null;
        }

        $rule = Value::map($rule);
        $clear = Value::strings($rule['clear'] ?? null);

        return new TemplateRule(
            Value::int($rule['lifetime'] ?? $this->defaultLifetime()),
            Value::strings($rule['parameters'] ?? null),
            $clear === [] ? [TemplateRule::CLEAR_ALL] : $clear,
            Value::string($rule['csrf'] ?? config('lynx-cache.pages.csrf')) === TokenRefresh::REFRESH,
        );
    }

    public function defaultLifetime(): int
    {
        $own = $this->settingsValue(PageCacheSchema::LIFETIME);

        return $own === null || $own === '' ? Value::int(config('lynx-cache.pages.lifetime')) : Value::int($own);
    }

    /**
     * The config's rules with the settings page's on top.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $own = json_decode(Value::string($this->settingsValue(PageCacheSchema::RULES)), true);

        return array_replace(Value::map(config('lynx-cache.pages.templates')), Value::map($own));
    }

    private function settingsValue(string $field): mixed
    {
        $settings = lynx()->settings(PageCacheSchema::SETTINGS_TEMPLATE);

        return $settings instanceof Page && $settings->templateField($field) !== null ? $settings->getUnformatted($field) : null;
    }
}
