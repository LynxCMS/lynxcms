<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * A string a template declares with @string (ADR-0047): its key, the text in code (one text, or
 * per language code), and how editors see and visitors get it.
 *
 * @api
 */
final readonly class StringDefinition
{
    public const string PAGE = 'page';

    public const string SITE = 'site';

    /**
     * @param  string|array<string, string>  $default
     */
    public function __construct(
        public string $key,
        public string|array $default,
        public ?string $label = null,
        public string $scope = self::PAGE,
        public string $format = 'plain',
        /** The file declaring it, below the views folder, e.g. "layouts/site.blade.php". */
        public string $view = '',
    ) {}

    /**
     * Plain data for caches: Laravel 13 refuses to unserialize objects from the cache by default
     * (cache.serializable_classes).
     *
     * @return array{key: string, default: string|array<string, string>, label: string|null, scope: string, format: string, view: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'default' => $this->default, 'label' => $this->label, 'scope' => $this->scope, 'format' => $this->format, 'view' => $this->view];
    }

    /**
     * @param  array<array-key, mixed>  $data  from toArray()
     */
    public static function fromArray(array $data): self
    {
        $default = $data['default'] ?? '';

        return new self(
            Value::string($data['key'] ?? ''),
            is_array($default) ? array_map(Value::string(...), Value::map($default)) : Value::string($default),
            Value::nullableString($data['label'] ?? null),
            Value::string($data['scope'] ?? self::PAGE, self::PAGE),
            Value::string($data['format'] ?? 'plain', 'plain'),
            Value::string($data['view'] ?? ''),
        );
    }

    /**
     * The scope of a string that does not name one, by the view it is in: site in a layout
     * folder (lynx.strings.site_folders), page elsewhere (ADR-0051).
     */
    public static function defaultScopeFor(string $path): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $folders = Value::strings(config('lynx.strings.site_folders', ['layouts']));

        return array_intersect(array_slice($segments, 0, -1), $folders) === [] ? self::PAGE : self::SITE;
    }

    /** The code text in a language (current by default), with fallback. */
    public function defaultIn(?string $language = null): string
    {
        return LocalizedText::from($this->default)->in($language);
    }

    public function isSite(): bool
    {
        return $this->scope === self::SITE;
    }

    /** What editors read: the label, else the key. */
    public function title(): string
    {
        return $this->label ?? $this->key;
    }
}
