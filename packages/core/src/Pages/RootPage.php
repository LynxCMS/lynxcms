<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Creates the home page on install, with a translatable "title" field and a "home" template.
 *
 * @internal
 */
final class RootPage implements Flushable
{
    private ?int $id = null;

    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly TemplateRegistry $templates,
    ) {}

    public function ensure(ActionContext $context): bool
    {
        if (DB::table(Tables::PAGES)->whereNull('parent_id')->whereNull('draft_of')->exists()) {
            return false;
        }

        if ($this->fields->find('title') === null) {
            $context->run(CreateField::class, ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'translatable' => true]);
        }

        if ($this->templates->find('home') === null) {
            $context->run(CreateTemplate::class, ['name' => 'home', 'label' => 'Home', 'fields' => [['name' => 'title', 'required' => true]]]);
        }

        $context->run(SavePage::class, ['template' => 'home', 'values' => ['title' => 'Home'], 'names' => [(string) app(LanguageRegistry::class)->default()->code => '']]);

        return true;
    }

    /**
     * Once per request: a layout and the SEO module both ask for the home page on every page. Only
     * a found id is kept, since installing asks before it creates the page, in the same action.
     */
    public function id(): ?int
    {
        if ($this->id === null) {
            $id = DB::table(Tables::PAGES)->whereNull('parent_id')->whereNull('draft_of')->value('id');
            $this->id = $id === null ? null : Value::int($id);
        }

        return $this->id;
    }

    public function flush(): void
    {
        $this->id = null;
    }
}
