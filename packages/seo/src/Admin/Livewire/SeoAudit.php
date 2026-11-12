<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Seo\SeoAuditor;

/**
 * The SEO screen's Audit tab: published pages whose titles or descriptions need work.
 *
 * @internal
 */
final class SeoAudit extends Component
{
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('seo.manage');
    }

    public function render(): View
    {
        return view('lynx-seo::admin.livewire.audit', app(SeoAuditor::class)->audit());
    }
}
