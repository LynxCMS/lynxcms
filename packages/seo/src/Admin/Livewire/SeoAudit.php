<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Seo\SeoAuditor;

/**
 * The SEO screen's Audit tab: published pages whose titles or descriptions need work.
 *
 * @internal
 */
final class SeoAudit extends Component
{
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('seo.manage'), 403);
    }

    public function render(): View
    {
        return view('lynx-seo::admin.livewire.audit', app(SeoAuditor::class)->audit());
    }
}
