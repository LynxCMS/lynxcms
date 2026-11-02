<?php

declare(strict_types=1);

namespace LynxCms\Seo\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\JsonLd\SchemaTypes;
use LynxCms\Seo\JsonLd\ValueConverter;
use LynxCms\Seo\SeoSchema;

/**
 * The SEO settings page's values that every page shares, read once and kept in the cache until
 * the settings page or the schema changes (ADR-0098). One copy per host and language: the values
 * hold absolute URLs and translated text. Plain arrays only, so a cached copy outlives a deploy.
 *
 * @internal
 */
final class SiteSettings implements Flushable
{
    /** Bump when the cached shape changes, so copies from an older release are not read. */
    public const string CACHE_KEY = 'lynx-seo.site-settings.v1';

    /** They change what the settings page holds without a ContentChanged event. */
    public const array SCHEMA_ACTIONS = [CreateField::class, UpdateField::class, DeleteField::class, CreateTemplate::class, UpdateTemplate::class, DeleteTemplate::class];

    /** @var array<string, array<string, mixed>> this request's copies, by host and language */
    private array $values = [];

    public function __construct(
        private readonly Repository $cache,
        private readonly PageText $text,
        private readonly ShareImage $images,
        private readonly ValueConverter $converter,
    ) {}

    public function siteName(): string
    {
        return Value::string($this->values()['site_name'] ?? null);
    }

    public function titlePattern(): string
    {
        return Value::string($this->values()['title_pattern'] ?? null);
    }

    public function defaultDescription(): string
    {
        return Value::string($this->values()['default_description'] ?? null);
    }

    public function twitter(): string
    {
        return Value::string($this->values()['twitter'] ?? null);
    }

    /** @return array{url: string, width: int, height: int, alt: string}|null */
    public function defaultImage(): ?array
    {
        $image = Value::map($this->values()['default_image'] ?? null);

        return $image === [] ? null : ['url' => Value::string($image['url'] ?? null), 'width' => Value::int($image['width'] ?? null), 'height' => Value::int($image['height'] ?? null), 'alt' => Value::string($image['alt'] ?? null)];
    }

    /** @return array{name: string, is_person: bool, logo: mixed, same_as: mixed}|null when the settings page is missing */
    public function organization(): ?array
    {
        $organization = Value::map($this->values()['organization'] ?? null);

        return $organization === [] ? null : [
            'name' => Value::string($organization['name'] ?? null),
            'is_person' => (bool) ($organization['is_person'] ?? false),
            'logo' => $organization['logo'] ?? null,
            'same_as' => $organization['same_as'] ?? null,
        ];
    }

    /** @return array<int, array{enabled: bool, template: string, type: string, lines: string}> */
    public function mappings(): array
    {
        $mappings = [];

        foreach (Value::map($this->values()['mappings'] ?? null) as $index => $item) {
            $item = Value::map($item);
            $mappings[(int) $index] = ['enabled' => (bool) ($item['enabled'] ?? false), 'template' => Value::string($item['template'] ?? null), 'type' => Value::string($item['type'] ?? null), 'lines' => Value::string($item['lines'] ?? null)];
        }

        return $mappings;
    }

    /** After the settings page or the schema changed: every host's and language's copy. */
    public function forget(): void
    {
        $this->values = [];
        $this->cache->forget(self::CACHE_KEY);
    }

    /** A schema action's hook runs inside its transaction: a rollback keeps the copies. */
    public function forgetAfterCommit(): void
    {
        DB::afterCommit($this->forget(...));
    }

    /**
     * A content change clears the copies when it touches the settings page they were read from,
     * or any page may show it (a language). It never looks the settings page up: inside an action
     * still installing it, SettingsPages would remember the page as missing.
     */
    public function handle(ContentChanged $event): void
    {
        $pages = $event->affectedPages();
        $readFrom = array_map(fn (mixed $copy): mixed => Value::map($copy)['page'] ?? null, Value::map($this->cache->get(self::CACHE_KEY)));
        $isTouched = array_filter($readFrom, fn (mixed $id): bool => $id === null || in_array($id, $pages ?? [], true)) !== [];

        if ($pages === null || $isTouched) {
            $this->forget();
        }
    }

    public function flush(): void
    {
        $this->values = [];
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        $scope = rtrim(url('/'), '/').'|'.lynx()->languages->current()->code;

        return $this->values[$scope] ??= $this->cached($scope);
    }

    /**
     * One cache entry holds every copy, so forget() clears them all in one call.
     *
     * @return array<string, mixed>
     */
    private function cached(string $scope): array
    {
        $copies = Value::map($this->cache->get(self::CACHE_KEY));

        if (! is_array($copies[$scope] ?? null)) {
            $copies[$scope] = $this->read();
            $this->cache->forever(self::CACHE_KEY, $copies);
        }

        return Value::map($copies[$scope]);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);

        return [
            'page' => $settings?->id,
            'site_name' => $this->text->of($settings, SeoSchema::SITE_NAME),
            'title_pattern' => $this->text->of($settings, SeoSchema::TITLE_PATTERN),
            'default_description' => $this->text->of($settings, SeoSchema::DEFAULT_DESCRIPTION),
            'twitter' => $this->text->of($settings, SeoSchema::TWITTER),
            'default_image' => $this->images->first([[$settings, SeoSchema::DEFAULT_IMAGE]]),
            'organization' => $settings === null ? null : $this->organizationOf($settings),
            'mappings' => Mappings::items($settings),
        ];
    }

    /** @return array<string, mixed> */
    private function organizationOf(Page $settings): array
    {
        $type = $settings->templateField(SeoSchema::ORG_TYPE) === null ? null : $settings->get(SeoSchema::ORG_TYPE);

        return [
            'name' => $this->text->of($settings, SeoSchema::ORG_NAME),
            'is_person' => $type instanceof Choice && $type->value === SeoSchema::ORG_PERSON,
            'logo' => $settings->templateField(SeoSchema::ORG_LOGO) === null ? null : $this->converter->convert($settings->get(SeoSchema::ORG_LOGO), SchemaTypes::IMAGE, 'logo'),
            'same_as' => $this->converter->convert($settings->getUnformatted(SeoSchema::ORG_SAME_AS), SchemaTypes::LINES, 'sameAs'),
        ];
    }
}
