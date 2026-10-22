<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * lynx()->settings('settings'): the page on a singleton template, loaded once per request.
 * Its translatable values follow the current language like any other page (ADR-0008).
 *
 * @internal
 */
final class SettingsPages implements Flushable
{
    /** @var array<string, int|null> */
    private array $ids = [];

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly PageLoader $loader,
    ) {}

    public function get(string $template): ?Page
    {
        if (! array_key_exists($template, $this->ids)) {
            $definition = $this->templates->find($template);
            $id = $definition === null ? null : DB::table(Tables::PAGES)->where('template_id', $definition->id)->whereNull('draft_of')->orderBy('id')->value('id');
            $this->ids[$template] = $id === null ? null : Value::int($id);
        }

        return $this->ids[$template] === null ? null : $this->loader->find($this->ids[$template]);
    }

    public function flush(): void
    {
        $this->ids = [];
    }
}
