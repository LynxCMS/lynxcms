<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use LynxCms\Admin\Tree\TreeUi;

function renderedClass(string $blade): string
{
    preg_match('/class="([^"]*)"/', Blade::render($blade), $match);

    return html_entity_decode($match[1] ?? '');
}

/** @return list<string> */
function classList(string $classes): array
{
    $list = preg_split('/\s+/', trim($classes)) ?: [];
    sort($list);

    return $list;
}

it('gives tree actions the classes BlatUI\'s button component renders', function (string $size, string $extra): void {
    $component = renderedClass('<x-lynx-admin::ui.button variant="ghost" size="'.$size.'" class="'.$extra.'">x</x-lynx-admin::ui.button>');

    expect(classList(TreeUi::button('ghost', $size, $extra)))->toBe(classList($component));
})->with([['xs', ''], ['icon-xs', ''], ['xs', 'text-destructive hover:text-destructive']]);

it('renders each icon once per request', function (): void {
    expect(TreeUi::icon('folder', 'size-4'))->toBe(TreeUi::icon('folder', 'size-4'))
        ->and((string) TreeUi::icon('folder', 'size-4'))->toContain('<svg')->toContain('aria-hidden="true"');
});
