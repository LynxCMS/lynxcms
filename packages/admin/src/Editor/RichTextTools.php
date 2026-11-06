<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Fields\Types\TextareaType;

/**
 * The toolbar buttons of a rich text field's extra tools (its "tools" setting, ADR-0107), in one
 * order whatever order the setting keeps. Each button names its command in resources/js/rich-text-tools.js.
 *
 * @internal
 */
final class RichTextTools
{
    /** Buttons per tool: the editor's tool name, a Lucide icon and the label's key in inputs.rich_text. */
    private const array BUTTONS = [
        'superscript' => [['superscript', 'superscript', 'superscript']],
        'subscript' => [['subscript', 'subscript', 'subscript']],
        'underline' => [['underline', 'underline', 'underline']],
        'highlight' => [['highlight', 'highlighter', 'highlight']],
        'code' => [['code', 'code', 'code']],
        'code_block' => [['codeBlock', 'square-code', 'code_block']],
        'horizontal_rule' => [['horizontalRule', 'minus', 'horizontal_rule']],
        'text_align' => [
            ['alignLeft', 'text-align-start', 'align_left'],
            ['alignCenter', 'text-align-center', 'align_center'],
            ['alignRight', 'text-align-end', 'align_right'],
            ['alignJustify', 'text-align-justify', 'align_justify'],
        ],
    ];

    /**
     * @param  list<string>  $tools  the field's "tools" setting; unknown names are ignored
     * @return list<array{tool: string, icon: string, label: string}>
     */
    public function buttons(array $tools): array
    {
        $buttons = [];

        foreach (array_intersect(TextareaType::TOOLS, $tools) as $tool) {
            foreach (self::BUTTONS[$tool] as [$name, $icon, $label]) {
                $buttons[] = ['tool' => $name, 'icon' => 'lucide-'.$icon, 'label' => 'lynx-admin::inputs.rich_text.'.$label];
            }
        }

        return $buttons;
    }
}
