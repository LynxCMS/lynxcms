<?php

declare(strict_types=1);

namespace LynxCms\Seo\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\JsonLd\SchemaTypes;
use LynxCms\Seo\SeoSchema;

/**
 * Maps a template to a schema.org type for structured data, as the admin's Structured data tab
 * does: each property names its source (a field, @title, @url, @published, @modified,
 *
 * @organization, or =a fixed value). Replaces the template's mapping; others stay. The settings
 * page's validation refuses a mapping that would make broken structured data.
 */
final class MapTemplate extends Action
{
    public static function name(): string
    {
        return 'seo.map_template';
    }

    public static function description(): string
    {
        return 'Map a template with URLs to a schema.org type for structured data (JSON-LD), replacing its mapping. "map" gives each property its source: a field name, @title, @url, @published, @modified, @organization, or =fixed text; dotted properties nest (location.name). Use seo_inspect afterwards to check a page.';
    }

    public static function permission(): string
    {
        return 'seo.manage';
    }

    public static function rules(): array
    {
        return [
            'template' => ['required', 'string'],
            'type' => ['required', 'string'],
            'map' => ['required', 'array', 'min:1'],
            'map.*' => ['required', 'string'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'template' => $schema->string()->description('The template name, e.g. "course".')->required(),
            'type' => $schema->string()->description('The schema.org type: '.implode(', ', SchemaTypes::names()).'.')->required(),
            'map' => $schema->object()->description('Property => source, e.g. {"name": "@title", "startDate": "starts", "offers.priceCurrency": "=EUR"}.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $template = $input->string('template') ?? '';
        $type = $input->string('type') ?? '';
        $this->assertTemplate($template);

        if (! SchemaTypes::has($type)) {
            throw ValidationException::withMessages(['type' => "[{$type}] is not a type Lynx maps. Use one of: ".implode(', ', SchemaTypes::names()).'.']);
        }

        $lines = [];
        foreach (Value::map($input->get('map')) as $property => $source) {
            $lines[] = trim((string) $property).': '.trim(Value::string($source));
        }

        $context->run(InstallSeo::class);
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
        $mappings = array_values(array_filter(Mappings::items($settings), fn (array $mapping): bool => $mapping['template'] !== $template));
        $mappings[] = ['enabled' => true, 'template' => $template, 'type' => $type, 'lines' => implode("\n", $lines)];

        $context->changes->schema('seo', "Map template [{$template}] to {$type}", ['template' => $template, 'type' => $type]);
        $context->run(SavePage::class, ['id' => $settings?->id, 'values' => [SeoSchema::SCHEMAS => Mappings::toItems($mappings)]]);

        return true;
    }

    private function assertTemplate(string $template): void
    {
        $definition = lynx()->templates->find($template);

        if ($definition === null || ! $definition->hasUrl()) {
            throw ValidationException::withMessages(['template' => $definition === null
                ? "Template [{$template}] does not exist."
                : "Template [{$template}] has no URL: search engines never see its pages."]);
        }
    }
}
