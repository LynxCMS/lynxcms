<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\Editor\Converters\ChoiceConverter;
use LynxCms\Admin\Editor\Converters\ComponentConverter;
use LynxCms\Admin\Editor\Converters\DateConverter;
use LynxCms\Admin\Editor\Converters\DecimalConverter;
use LynxCms\Admin\Editor\Converters\IntegerConverter;
use LynxCms\Admin\Editor\Converters\ItemsConverter;
use LynxCms\Admin\Editor\Converters\PageConverter;
use LynxCms\Admin\Editor\Converters\TextConverter;
use LynxCms\Admin\Editor\Converters\ToggleConverter;

/**
 * How the page editor edits each field type: the Blade view of its input, the FormConverter
 * between stored and form values, and optionally a view that displays stored values read only
 * (ADR-0042). Modules register their own types:
 * app(FieldInputs::class)->register('rating', 'my-module::inputs.rating', RatingConverter::class).
 * The input view receives $field (FieldView), $code (language), $model (the wire:model path),
 * $value (the current form value), $placeholder (the fallback value, if any), $disabled and $live
 * (bind with wire:model.live when true). Types without an input are shown read only.
 *
 * @api
 */
final class FieldInputs
{
    /** @var array<string, array{view: string, converter: class-string<FormConverter>|null, display: string|null}> */
    private array $inputs = [];

    public function __construct()
    {
        $this->register('text', 'lynx-admin::inputs.text', TextConverter::class);
        $this->register('email', 'lynx-admin::inputs.email', TextConverter::class);
        $this->register('url', 'lynx-admin::inputs.url', TextConverter::class);
        $this->register('icon', 'lynx-admin::inputs.icon', TextConverter::class, 'lynx-admin::values.icon');
        $this->register('textarea', 'lynx-admin::inputs.textarea', TextConverter::class);
        $this->register('integer', 'lynx-admin::inputs.integer', IntegerConverter::class);
        $this->register('decimal', 'lynx-admin::inputs.decimal', DecimalConverter::class);
        $this->register('choice', 'lynx-admin::inputs.choice', ChoiceConverter::class);
        $this->register('date', 'lynx-admin::inputs.date', DateConverter::class);
        $this->register('time', 'lynx-admin::inputs.time', TextConverter::class);
        $this->register('toggle', 'lynx-admin::inputs.toggle', ToggleConverter::class);
        $this->register('page', 'lynx-admin::inputs.page', PageConverter::class);
        $this->register('page_table', 'lynx-admin::inputs.page-table', PageConverter::class);
        $this->register('component', 'lynx-admin::inputs.component', ComponentConverter::class);
        $this->register('repeater', 'lynx-admin::inputs.items', ItemsConverter::class);
        $this->register('matrix', 'lynx-admin::inputs.items', ItemsConverter::class);
    }

    /**
     * Replacing only the view of a type keeps its converter and display view.
     *
     * @param  class-string<FormConverter>|null  $converter
     * @param  string|null  $display  view showing a stored value read only; gets $value (prepared by
     *                                the converter when it implements PreparesDisplay)
     */
    public function register(string $type, string $view, ?string $converter = null, ?string $display = null): void
    {
        $this->inputs[$type] = [
            'view' => $view,
            'converter' => $converter ?? ($this->inputs[$type]['converter'] ?? null),
            'display' => $display ?? ($this->inputs[$type]['display'] ?? null),
        ];
    }

    public function for(string $type): ?string
    {
        return $this->inputs[$type]['view'] ?? null;
    }

    public function converter(string $type): ?FormConverter
    {
        $class = $this->inputs[$type]['converter'] ?? null;
        $converter = $class === null ? null : app($class);

        return $converter instanceof FormConverter ? $converter : null;
    }

    public function display(string $type): ?string
    {
        return $this->inputs[$type]['display'] ?? null;
    }

    /**
     * A stored or form value ready for the editor.value view: prepared by the type's converter when
     * it implements PreparesDisplay (page references become [id, title] pairs), so the view runs no queries.
     */
    public function prepareDisplay(string $type, mixed $value): mixed
    {
        $converter = $this->converter($type);

        return $converter instanceof PreparesDisplay ? $converter->display($value) : $value;
    }
}
