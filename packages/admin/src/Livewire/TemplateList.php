<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Template;

/**
 * Setup > Templates: every template with its number of fields and pages and its flags.
 *
 * @api
 */
final class TemplateList extends Component
{
    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, 'You may not manage templates and fields.');
    }

    /** System templates (Lynx's and modules') are listed only when asked for. */
    public bool $showSystem = false;

    public function render(): View
    {
        $owners = app(SchemaOwners::class);
        $rows = [];

        foreach (lynx()->templates->all()->sortBy('name') as $template) {
            $owner = $owners->ownerOfTemplate((string) $template->name);

            if ($owner === null || $this->showSystem) {
                $rows[(string) $template->name] = $this->row($template) + ['owner' => $owner];
            }
        }

        return view('lynx-admin::livewire.template-list', ['rows' => $rows]);
    }

    /**
     * @return array{label: string, fields: int, pages: int, has_url: bool, singleton: bool}
     */
    private function row(Template $template): array
    {
        return [
            'label' => (string) $template->label,
            'fields' => lynx()->templates->fields($template)->count(),
            'pages' => lynx()->pages->count("template={$template->name}, include=all"),
            'has_url' => $template->hasUrl(),
            'singleton' => $template->isSingleton(),
        ];
    }
}
