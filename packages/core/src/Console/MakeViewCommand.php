<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use LynxCms\Core\Templates\Views\ViewStub;
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

    public function handle(TemplateRegistry $templates, ViewStub $stub): int
    {
        $layout = Value::nullableString($this->option('layout'));

        if ($this->option('all')) {
            foreach ($this->templatesWithPages($templates) as $template) {
                if (! File::exists($this->path($template))) {
                    $this->write($template, $stub->for($template, $layout, (bool) $this->option('all-tabs')));
                }
            }

            return self::SUCCESS;
        }

        $template = $templates->find(Value::string($this->argument('template')));

        if (! $template instanceof Template) {
            $this->components->error('No template is named ['.Value::string($this->argument('template')).']. Give a template, or --all.');

            return self::FAILURE;
        }

        if (File::exists($this->path($template)) && ! $this->option('force')) {
            $this->components->error('The view exists: '.$this->path($template).'. Add --force to replace it.');

            return self::FAILURE;
        }

        $this->write($template, $stub->for($template, $layout, (bool) $this->option('all-tabs')));

        return self::SUCCESS;
    }

    private function write(Template $template, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->path($template)));
        File::put($this->path($template), $contents);
        $this->components->info('Wrote '.$this->path($template).'.');
    }

    private function path(Template $template): string
    {
        $base = Value::string(Value::list(config('view.paths'))[0] ?? resource_path('views'));

        return $base.'/'.str_replace('.', '/', Value::string(config('lynx.views_path', 'templates'))).'/'.$template->name.'.blade.php';
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
