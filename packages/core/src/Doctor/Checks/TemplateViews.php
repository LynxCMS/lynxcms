<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Templates whose pages have a URL but no view of their own: those pages show in Lynx's plain
 * fallback view (TemplateView). Templates no page uses yet are left alone.
 *
 * @internal
 */
final readonly class TemplateViews implements Check
{
    public function __construct(private TemplateRegistry $templates, private ViewFactory $views) {}

    public function key(): string
    {
        return 'template-views';
    }

    public function label(): string
    {
        return 'Pages with a URL have their template\'s view';
    }

    public function run(): array
    {
        $directory = Value::string(config('lynx.views_path', 'templates'));
        $counts = DB::table(Tables::PAGES)->whereNull('draft_of')->groupBy('template_id')->selectRaw('template_id, count(*) as pages')->pluck('pages', 'template_id');
        $findings = [];

        foreach ($this->templates->all()->sortBy('name') as $template) {
            $pages = Value::int($counts[$template->id] ?? 0);
            $view = $directory.'.'.$template->name;

            if ($template->hasUrl() && $pages > 0 && ! $this->views->exists($view)) {
                $findings[] = $this->finding($template, $view, $pages);
            }
        }

        return $findings;
    }

    private function finding(Template $template, string $view, int $pages): Finding
    {
        $path = 'resources/views/'.str_replace('.', '/', $view).'.blade.php';

        return new Finding(
            Severity::Warning,
            "Template [{$template->name}] has no view: its {$pages} page(s) show in Lynx's plain fallback view.",
            "Run php artisan lynx:make:view {$template->name} for a starting view, or create {$path}.",
        );
    }
}
