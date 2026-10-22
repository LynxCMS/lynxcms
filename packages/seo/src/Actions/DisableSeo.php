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
use LynxCms\Core\Templates\Layout\TemplateLayout;
use LynxCms\Seo\SeoSchema;

/**
 * Turns SEO off for a template. Its pages keep their SEO values as orphans (rule 6), so turning
 * it back on brings them back.
 */
final class DisableSeo extends Action
{
    public static function name(): string
    {
        return 'seo.disable_template';
    }

    public static function description(): string
    {
        return 'Turn SEO off for a template: removes the SEO fields from it. Values are kept and come back when SEO is turned on again.';
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
        return ['template' => $schema->string()->description('The template name.')->required()];
    }

    /** @return bool whether the template changed */
    public function handle(Input $input, ActionContext $context): bool
    {
        $template = $input->string('template') ?? '';

        if (lynx()->templates->find($template) === null) {
            throw ValidationException::withMessages(['template' => "Template [{$template}] does not exist."]);
        }

        $present = array_values(array_intersect(SeoSchema::pageFields(), lynx()->templates->fields($template)->keys()->all()));

        if ($present !== []) {
            $context->run(UpdateTemplate::class, ['template' => $template, 'remove' => $present]);
            $this->removeEmptyTab($template, $context);
        }

        return $present !== [];
    }

    /** The SEO tab goes once nothing is left in it; fields an editor moved there keep it. */
    private function removeEmptyTab(string $template, ActionContext $context): void
    {
        $definition = lynx()->templates->get($template);
        $tabs = Value::list($definition->config()['tabs'] ?? []);
        $layout = TemplateLayout::for($definition);

        if (SeoSchema::hasTab($tabs) && $layout->blocks(SeoSchema::TAB) === [] && $layout->blocks(SeoSchema::TAB, TemplateLayout::SIDEBAR) === []) {
            $context->run(UpdateTemplate::class, ['template' => $template, 'tabs' => SeoSchema::withoutTab($tabs)]);
        }
    }
}
