<?php

declare(strict_types=1);

namespace LynxCms\Seo\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Seo\SeoSchema;

/**
 * Turns SEO on for a template: its pages get their own title, description, share image, noindex
 * and canonical fields. Installs the module first.
 */
final class EnableSeo extends Action
{
    public static function name(): string
    {
        return 'seo.enable_template';
    }

    public static function description(): string
    {
        return 'Turn SEO on for a template with URLs: adds the SEO title, description, share image, noindex and canonical fields to it, in an SEO tab (moving SEO fields still in their old place). Pages without values fall back to their title, content and the site defaults.';
    }

    public static function permission(): string
    {
        return 'seo.manage';
    }

    public static function rules(): array
    {
        return ['template' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['template' => $schema->string()->description('The template name, e.g. "article".')->required()];
    }

    /** @return bool whether the template changed */
    public function handle(Input $input, ActionContext $context): bool
    {
        $template = $input->string('template') ?? '';
        $definition = lynx()->templates->find($template);

        if ($definition === null) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] does not exist."]);
        }

        if (! $definition->hasUrl()) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] has no URL: search engines never see its pages."]);
        }

        $context->run(InstallSeo::class);
        $fields = lynx()->templates->fields($template);
        $missing = array_values(array_diff(SeoSchema::pageFields(), $fields->keys()->all()));
        $moving = array_values(array_filter(SeoSchema::pageFields(), fn (string $name): bool => $fields->get($name) instanceof TemplateField && $this->isWhereAdded($fields->get($name)->settings)));

        if ($missing === [] && $moving === []) {
            return false;
        }

        $context->run(UpdateTemplate::class, [
            'template' => $template,
            'tabs' => SeoSchema::withTab(Value::list($definition->config()['tabs'] ?? [])),
            'add' => [...SeoSchema::pageSpecs($missing), ...array_map(fn (string $name): array => ['name' => $name] + SeoSchema::placement($name), $moving)],
        ]);

        return true;
    }

    /**
     * Whether a field sits where SEO fields were added before the SEO tab: Content, main column,
     * no section. Fields an editor placed elsewhere stay there.
     *
     * @param  array<string, mixed>  $settings
     */
    private function isWhereAdded(array $settings): bool
    {
        return in_array($settings['tab'] ?? LayoutTab::CONTENT, ['', LayoutTab::CONTENT], true)
            && in_array($settings['column'] ?? TemplateLayout::MAIN, ['', TemplateLayout::MAIN], true)
            && ($settings['section'] ?? '') === '';
    }
}
