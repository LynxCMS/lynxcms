<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use LynxCms\Core\Templates\Views\ViewWriter;
use Override;

/**
 * Writes a starting view for a template, or with --all for every template whose pages have none,
 * into the app's first view path under lynx.views_path. An existing view is kept unless --force.
 * Fields on tabs other than Content are named in a comment, unless --all-tabs.
 */
final class MakeViewCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:make:view
        {template? : The template to write a view for}
        {--all : Write the missing view of every template with pages}
        {--layout= : A layout component to wrap the view in, e.g. layouts.site}
        {--all-tabs : Also print the fields on other editor tabs, such as SEO}
        {--force : Replace a view that exists}';

    #[Override]
    protected $description = 'Write a starting Blade view for a template, with its fields';

    public function handle(TemplateRegistry $templates, ViewWriter $views): int
    {
        $layout = Value::nullableString($this->option('layout'));
        $allTabs = (bool) $this->option('all-tabs');

        if ($this->option('all')) {
            foreach ($this->templatesWithPages($templates) as $template) {
                if (! $views->exists((string) $template->name)) {
                    $this->components->info('Wrote '.$views->write($template, $layout, $allTabs).'.');
                }
            }

            return self::SUCCESS;
        }

        $template = $templates->find(Value::string($this->argument('template')));

        if (! $template instanceof Template) {
            $this->components->error('No template is named ['.Value::string($this->argument('template')).']. Give a template, or --all.');

            return self::FAILURE;
        }

        if ($views->exists((string) $template->name) && ! $this->option('force')) {
            $this->components->error('The view exists: '.$views->path((string) $template->name).'. Add --force to replace it.');

            return self::FAILURE;
        }

        $this->components->info('Wrote '.$views->write($template, $layout, $allTabs).'.');

        return self::SUCCESS;
    }

    /**
     * Templates with a URL that pages use: those a visitor can see.
     *
     * @return list<Template>
     */
    private function templatesWithPages(TemplateRegistry $templates): array
    {
        return array_values($templates->all()
            ->filter(fn (Template $template): bool => $template->hasUrl() && lynx()->pages->find("template={$template->name}, include=all, limit=1")->count() > 0)
            ->all());
    }
}
