<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * Setup > Languages: every language with its URL prefix, fallback and tab label (ADR-0003).
 *
 * @api
 */
final class LanguageList extends Component
{
    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, Value::string(__('lynx-admin::languages.denied')));
    }

    public function render(): View
    {
        $languages = lynx()->languages;
        $rows = $languages->all()->map(fn (Language $language): array => [
            'code' => $language->code,
            'name' => $language->name,
            'label' => $language->tabLabel(),
            'prefix' => $language->url_prefix,
            'fallback' => $language->fallback_id === null ? null : $languages->find($language->fallback_id)?->code,
            'active' => $language->is_active,
            'default' => $language->isDefault(),
        ])->values()->all();

        return view('lynx-admin::livewire.language-list', ['rows' => $rows]);
    }
}
