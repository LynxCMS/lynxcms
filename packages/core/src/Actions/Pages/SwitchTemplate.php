<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\PageTemplateSwitched;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Pages\Drafts;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGuard;
use LynxCms\Core\Pages\PathBuilder;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\FamilyRules;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Switches a page to another template. Values of fields the new template lacks are kept as
 * orphans (ADR-0012): switching back restores them.
 */
final class SwitchTemplate extends Action
{
    use ResolvesPages;

    public function __construct(private readonly PageGuard $guard,
        private readonly TemplateRegistry $templates,
        private readonly PathBuilder $paths,
        private readonly FamilyRules $family,
    ) {}

    public static function name(): string
    {
        return 'pages.switch_template';
    }

    public static function permission(): string
    {
        return 'pages.template';
    }

    public static function description(): string
    {
        return 'Switch a page to another template. Values of fields the new template lacks are kept (orphaned) and come back if you switch back. Use a dry run to see which fields would be orphaned.';
    }

    public static function rules(): array
    {
        return ['page' => ['required'], 'template' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id, default-language path or page name of the page.')->required(),
            'template' => $schema->string()->description('Name of the new template.')->required(),
        ];
    }

    /**
     * @return array{page: Page, orphaned: list<string>}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $page = $this->resolvePageReference($input->get('page'));
        $this->guard->assertMovable($page, 'switched to another template');

        if (app(Drafts::class)->of((int) $page->id) !== null) {
            throw ValidationException::withMessages(['page' => "Page [{$page->name()}] has a draft on its current template. Publish or discard the draft first."]);
        }

        $context->authorize('pages.template', $page);
        $current = $this->templates->get($page->templateId);
        $target = $this->templates->get((string) $input->string('template'));
        $context->authorize('pages.template', $page, $target);

        if ($current->allowedTemplates() !== [] && ! in_array($target->name, $current->allowedTemplates(), true)) {
            throw ValidationException::withMessages(['template' => "Pages on [{$current->name}] may only switch to: ".implode(', ', $current->allowedTemplates()).'.']);
        }

        $this->assertFamily($page, $target);

        if ($target->isSingleton() && DB::table(Tables::PAGES)->where('template_id', $target->id)->whereNull('draft_of')->exists()) {
            throw ValidationException::withMessages(['template' => "Template [{$target->name}] is a singleton and already has a page."]);
        }

        $orphaned = array_values(array_diff(
            $this->templates->fields($current)->keys()->all(),
            $this->templates->fields($target)->keys()->all(),
        ));

        DB::table(Tables::PAGES)->where('id', $page->id)->update(['template_id' => $target->id, 'updated_at' => now(), 'revision' => DB::raw('revision + 1')]);
        $page->templateId = $target->id;

        $context->changes->updated('page', $page->id, "Switch page {$page->name()} from {$current->name} to {$target->name}"
            .($orphaned !== [] ? '; orphaned (kept): '.implode(', ', $orphaned) : ''), ['from' => $current->name, 'to' => $target->name, 'orphaned' => $orphaned]);

        $context->event(new PageTemplateSwitched((int) $page->id, (string) $current->name, (string) $target->name));

        if ($current->hasUrl() !== $target->hasUrl()) {
            $this->paths->rebuild((int) $page->id, descendants: true, changes: $context->changes);
        }

        return ['page' => $page, 'orphaned' => $orphaned];
    }

    /** The new template must fit under the page's parent, and the page's children under it (ADR-0032). */
    private function assertFamily(Page $page, Template $target): void
    {
        $this->family->assertAllowed($target, $page->parentId, 'template');
        $childTemplates = DB::table(Tables::PAGES)->where('parent_id', $page->id)->distinct()->pluck('template_id');

        foreach ($childTemplates as $templateId) {
            $child = $this->templates->get(Value::int($templateId));

            if (! $this->family->allows($target, $child)) {
                throw ValidationException::withMessages(['template' => "Pages using [{$target->name}] cannot have the children this page has (template [{$child->name}])."]);
            }
        }
    }
}
