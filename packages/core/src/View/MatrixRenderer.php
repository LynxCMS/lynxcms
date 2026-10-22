<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use Illuminate\Contracts\View\Factory;
use LynxCms\Core\Pages\Items\Item;
use LynxCms\Core\Pages\Items\ItemList;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Renders matrix blocks with a view per type (ADR-0045), like ProcessWire's render files:
 * {views_path}.fields.{field}.{template}.{type} first, then {views_path}.fields.{field}.{type}.
 * The view gets $block (also $item), $page and $loop (index, iteration, first, last, count).
 * Without a view a block renders nothing, or an HTML comment when app.debug is on.
 *
 * @internal
 */
final readonly class MatrixRenderer
{
    public function __construct(private Factory $views) {}

    public function render(ItemList|Item|null $items, ?Page $page = null): string
    {
        $blocks = $items instanceof ItemList ? $items->enabled() : ($items === null ? [] : [$items]);
        $html = '';

        foreach ($blocks as $index => $block) {
            $html .= $this->item($block, $page, (object) [
                'index' => $index,
                'iteration' => $index + 1,
                'first' => $index === 0,
                'last' => $index === count($blocks) - 1,
                'count' => count($blocks),
            ]);
        }

        return $html;
    }

    public function item(Item $block, ?Page $page = null, ?object $loop = null): string
    {
        $base = Value::string(config('lynx.views_path', 'templates'), 'templates').'.fields.'.$block->field();
        $type = (string) $block->type();
        $candidates = $page instanceof Page ? ["{$base}.{$page->template()->name}.{$type}", "{$base}.{$type}"] : ["{$base}.{$type}"];

        foreach ($candidates as $view) {
            if ($this->views->exists($view)) {
                return $this->views->make($view, ['block' => $block, 'item' => $block, 'page' => $page, 'loop' => $loop])->render();
            }
        }

        return config('app.debug') ? "<!-- No view for {$block->field()} block [{$type}]: create ".str_replace('.', '/', end($candidates)).'.blade.php -->' : '';
    }
}
