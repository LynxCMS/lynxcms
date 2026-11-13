<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Where a template's view and its matrix blocks' views live (the first view path, under
 * lynx.views_path) and writing starting ones there: lynx:make:view and lynx:draft. Block views
 * sit where @matrix looks for them: {views_path}/fields/{field}/{type}.blade.php.
 *
 * @internal
 */
final readonly class ViewWriter
{
    public function __construct(private ViewStub $stub, private BlockViewStub $blockStub, private TemplateRegistry $templates) {}

    public function path(string $template): string
    {
        return $this->base().'/'.$this->relativePath($template);
    }

    /** The path below the view folder, such as templates/course.blade.php. */
    public function relativePath(string $template): string
    {
        return $this->viewsPath().'/'.$template.'.blade.php';
    }

    public function exists(string $template): bool
    {
        return File::exists($this->path($template));
    }

    /** Writes the starting view, replacing one that exists, and returns its path. */
    public function write(Template $template, ?string $layout = null, bool $allTabs = false): string
    {
        return $this->put($this->path((string) $template->name), $this->stub->for($template, $layout, $allTabs));
    }

    /** The path below the view folder, such as templates/fields/blocks/quote.blade.php. */
    public function blockRelativePath(string $field, string $type): string
    {
        return $this->viewsPath().'/fields/'.$field.'/'.$type.'.blade.php';
    }

    public function blockExists(string $field, string $type): bool
    {
        return File::exists($this->base().'/'.$this->blockRelativePath($field, $type));
    }

    /** Writes the starting view of one block type, replacing one that exists, and returns its path. */
    public function writeBlock(Field $matrix, string $type): string
    {
        return $this->put($this->base().'/'.$this->blockRelativePath((string) $matrix->name, $type), $this->blockStub->for($matrix, $type));
    }

    /**
     * The block types of the template's matrix fields.
     *
     * @return list<array{field: Field, type: string}>
     */
    public function blocks(Template $template): array
    {
        $blocks = [];

        foreach ($this->templates->fields($template) as $templateField) {
            $type = $templateField->field->fieldType();

            foreach ($type instanceof MatrixType ? $type->slugs($templateField->field->config()) : [] as $slug) {
                $blocks[] = ['field' => $templateField->field, 'type' => $slug];
            }
        }

        return $blocks;
    }

    private function put(string $path, string $contents): string
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }

    private function base(): string
    {
        return Value::string(Value::list(config('view.paths'))[0] ?? resource_path('views'));
    }

    private function viewsPath(): string
    {
        return str_replace('.', '/', Value::string(config('lynx.views_path', 'templates')));
    }
}
