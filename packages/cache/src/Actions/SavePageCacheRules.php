<?php

declare(strict_types=1);

namespace LynxCms\Cache\Actions;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use LynxCms\Cache\PageCacheSchema;
use LynxCms\Cache\Pages\TokenRefresh;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Saves the page cache's template rules and default lifetime on its settings page. Rules saved
 * here win over lynx-cache.pages.templates for the same template; "*" is every other template.
 */
final class SavePageCacheRules extends Action
{
    private const string ANY_TEMPLATE = '*';

    public static function name(): string
    {
        return 'page_cache.save_rules';
    }

    public static function description(): string
    {
        return 'Set which templates the page cache stores and how: per template (or "*" for the rest), cache on or off, lifetime in seconds (0 until content changes), allowed query parameters, what a change clears (self, parents, children, siblings, home, all), and csrf "refresh" to store pages with forms (or "skip"). Also the default lifetime.';
    }

    public static function permission(): string
    {
        return 'page_cache.manage';
    }

    public static function rules(): array
    {
        return [
            'lifetime' => ['nullable', 'integer', 'min:0'],
            'templates' => ['nullable', 'array', self::knownTemplates(...)],
            'templates.*' => ['array'],
            'templates.*.cache' => ['boolean'],
            'templates.*.lifetime' => ['nullable', 'integer', 'min:0'],
            'templates.*.parameters' => ['nullable', 'array'],
            'templates.*.parameters.*' => ['string', 'regex:/^[A-Za-z0-9_.\-\[\]]+$/'],
            'templates.*.clear' => ['nullable', 'array'],
            'templates.*.clear.*' => ['string', Rule::in(PageCacheSchema::SCOPES)],
            'templates.*.csrf' => ['nullable', Rule::in(['skip', TokenRefresh::REFRESH])],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'lifetime' => $schema->integer()->description('Default lifetime in seconds; 0 keeps pages until content changes.'),
            'templates' => $schema->object()->description('Template name (or "*") => {"cache": bool, "lifetime": int, "parameters": [string], "clear": [scope], "csrf": "skip"|"refresh"}. Templates left out keep the config rule.'),
        ];
    }

    /** @return int how many template rules were saved */
    public function handle(Input $input, ActionContext $context): int
    {
        $rules = [];

        foreach (Value::map($input->get('templates')) as $template => $rule) {
            $rules[$template] = $this->ruleFrom(Value::map($rule));
        }

        $lifetime = Value::nullableInt($input->get('lifetime'));
        $context->run(InstallPageCache::class);
        // Found by selector: lynx()->settings() may still hold the answer from before the install.
        $settingsId = lynx()->pages->findOne('template='.PageCacheSchema::SETTINGS_TEMPLATE.', include=all')?->id;
        $context->run(SavePage::class, ['id' => $settingsId, 'values' => [
            PageCacheSchema::LIFETIME => $lifetime,
            PageCacheSchema::RULES => json_encode($rules, JSON_THROW_ON_ERROR),
        ]]);
        $context->changes->updated('page_cache', null, 'Page cache rules saved for '.count($rules).' '.(count($rules) === 1 ? 'template' : 'templates'));

        return count($rules);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{lifetime?: int, parameters: list<string>, clear: list<string>, csrf?: string}|false
     */
    private function ruleFrom(array $rule): array|false
    {
        if (! Value::bool($rule['cache'] ?? true, true)) {
            return false;
        }

        $lifetime = Value::nullableInt($rule['lifetime'] ?? null);

        return [
            ...($lifetime === null ? [] : ['lifetime' => $lifetime]),
            'parameters' => Value::strings($rule['parameters'] ?? null),
            'clear' => Value::strings($rule['clear'] ?? null),
            ...(isset($rule['csrf']) ? ['csrf' => Value::string($rule['csrf'])] : []),
        ];
    }

    private static function knownTemplates(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (array_keys(Value::map($value)) as $template) {
            if ($template !== self::ANY_TEMPLATE && lynx()->templates->find($template) === null) {
                $fail("Template [{$template}] does not exist.");
            }
        }
    }
}
