<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use Illuminate\Contracts\Support\Htmlable;
use LynxCms\Core\Pages\FieldValue;
use LynxCms\Core\Pages\TranslatableValue;
use Stringable;

/**
 * Renders a field value for HTML. When a translatable value comes from a fallback language it is
 * wrapped with a lang attribute, so screen readers and search engines read it correctly.
 *
 * @api
 */
final readonly class FieldRenderer
{
    public function render(mixed $value): string
    {
        if ($value instanceof TranslatableValue) {
            $html = $value->toHtml();

            if (! $value->isFallback() || $html === '') {
                return $html;
            }

            $tag = ($value->field()->config()['format'] ?? 'plain') === 'plain' ? 'span' : 'div';

            return "<{$tag} lang=\"".e((string) $value->lang())."\">{$html}</{$tag}>";
        }

        if ($value instanceof FieldValue || $value instanceof Htmlable) {
            return $value->toHtml();
        }

        return e(is_scalar($value) || $value instanceof Stringable ? (string) $value : '');
    }
}
