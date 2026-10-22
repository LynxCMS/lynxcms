<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use LynxCms\Core\Lynx;
use Symfony\Component\Finder\Finder;

beforeEach(fn (): Lynx => courseSite());

/**
 * @return list<string> names of --lynx-* variables matched by a regex in the given text
 */
function lynxVariables(string $pattern, string $text): array
{
    preg_match_all($pattern, $text, $matches);

    return array_values(array_unique($matches[1]));
}

function adminPath(string $path): string
{
    return dirname(__DIR__, 3).'/packages/admin/'.$path;
}

it('documents every theme variable the admin reads in the theme stub', function (): void {
    $used = lynxVariables('/var\((--lynx-[a-z0-9-]+)/', file_get_contents(adminPath('resources/css/admin.css')));

    foreach (Finder::create()->files()->in(adminPath('resources/views')) as $view) {
        $used = [...$used, ...lynxVariables('/var\((--lynx-[a-z0-9-]+)/', $view->getContents())];
    }

    $defined = lynxVariables('/(--lynx-[a-z0-9-]+)\s*:/', file_get_contents(adminPath('resources/css/theme.stub.css')));

    expect(array_values(array_diff(array_unique($used), $defined)))->toBe([]);
});

it('renders the configured BlatUI presets on <html>, and nothing unsafe', function (): void {
    config(['lynx-admin.appearance' => ['base' => 'zinc', 'theme' => 'blue', 'radius' => '0.5', 'font' => '"><script>', 'unknown' => 'x']]);

    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/pages')
        ->assertSee('data-base="zinc" data-theme="blue" data-radius="0.5"', false)
        ->assertDontSee('data-font=', false)
        ->assertDontSee('<script>"', false);
});

it('serves no presets of its own: BlatUI presets are attributes', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/assets/themes/flux.css')->assertNotFound();
});
