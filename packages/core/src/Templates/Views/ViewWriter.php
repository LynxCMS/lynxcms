<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use Illuminate\Support\Facades\File;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;

/**
 * Where a template's view lives (the first view path, under lynx.views_path) and writing a starting
 * one there: lynx:make:view and lynx:draft.
 *
 * @internal
 */
final readonly class ViewWriter
{
    public function __construct(private ViewStub $stub) {}

    public function path(string $template): string
    {
        $base = Value::string(Value::list(config('view.paths'))[0] ?? resource_path('views'));

        return $base.'/'.$this->relativePath($template);
    }

    /** The path below the view folder, such as templates/course.blade.php. */
    public function relativePath(string $template): string
    {
        return str_replace('.', '/', Value::string(config('lynx.views_path', 'templates'))).'/'.$template.'.blade.php';
    }

    public function exists(string $template): bool
    {
        return File::exists($this->path($template));
    }

    /** Writes the starting view, replacing one that exists, and returns its path. */
    public function write(Template $template, ?string $layout = null, bool $allTabs = false): string
    {
        $path = $this->path((string) $template->name);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->stub->for($template, $layout, $allTabs));

        return $path;
    }
}
