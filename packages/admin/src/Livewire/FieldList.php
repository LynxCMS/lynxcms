<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\FieldUsage;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;

/**
 * Setup > Fields: every field with its type and the templates that use it, filtered by type,
 * template and text. Filters stay in the URL.
 *
 * @api
 */
final class FieldList extends Component
{
    #[Url]
    public string $type = '';

    #[Url]
    public string $template = '';

    #[Url(as: 'q')]
    public string $search = '';

    /** System fields (Lynx's and modules') are listed only when asked for. */
    public bool $showSystem = false;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, 'You may not manage templates and fields.');
    }

    public function render(): View
    {
        $usage = app(FieldUsage::class);
        $usedBy = $usage->labels();
        $search = mb_strtolower(trim($this->search));
        $fields = lynx()->fields->all()
            ->filter(fn (Field $field): bool => $this->type === '' || $field->type === $this->type)
            ->filter(fn (Field $field): bool => $this->template === '' || in_array($this->template, $usage->templatesOf((string) $field->name), true))
            ->filter(fn (Field $field): bool => $search === '' || str_contains(mb_strtolower($field->name.' '.$field->label), $search))
            ->filter(fn (Field $field): bool => $this->showSystem || ! app(SchemaOwners::class)->isSystemField((string) $field->name))
            ->sortBy('name')
            ->values();

        return view('lynx-admin::livewire.field-list', [
            'fields' => $fields,
            'usedBy' => $usedBy,
            'owners' => app(SchemaOwners::class),
            'types' => array_keys(lynx()->fields->types()),
            'templates' => lynx()->templates->all()->map(fn (Template $template): string => (string) $template->name)->sort()->values()->all(),
        ]);
    }
}
