<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;
use LynxCms\Core\Templates\Layout\LayoutBlock;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;
use LynxCms\Seo\Actions\DisableSeo;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Actions\InstallSeo;
use LynxCms\Seo\SeoSchema;

/*
 * Turning SEO on puts its fields in an SEO tab, with "Hide from search engines" in the tab's
 * sidebar; turning it off removes the tab once nothing is left in it.
 */

beforeEach(fn (): Lynx => courseSite());

$layout = fn (): TemplateLayout => TemplateLayout::for(lynx()->templates->get('course'));
$names = fn (TemplateLayout $layout, string $column): array => array_map(fn (LayoutBlock $block): string => $block->field ?? '', $layout->blocks('seo', $column));

it('puts the SEO fields in their own tab', function () use ($layout, $names): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    $seo = $layout();

    expect(array_map(fn (LayoutTab $tab): string => $tab->key, $seo->tabs()))->toBe(['content', 'seo'])
        ->and($seo->tabOf(SeoSchema::TITLE))->toBe('seo')
        ->and($names($seo, TemplateLayout::SIDEBAR))->toBe([SeoSchema::NOINDEX])
        ->and($names($seo, TemplateLayout::MAIN))->not->toContain(SeoSchema::NOINDEX)->toContain(SeoSchema::DESCRIPTION);
});

it('keeps the tabs a template already has', function () use ($layout): void {
    lynx()->templates->update('course', ['tabs' => [['key' => 'extra', 'label' => 'Extra']]]);
    lynx()->run(EnableSeo::class, ['template' => 'course']);

    expect(array_map(fn (LayoutTab $tab): string => $tab->key, $layout()->tabs()))->toBe(['content', 'extra', 'seo']);
});

it('removes the SEO tab with the fields', function () use ($layout): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    lynx()->run(DisableSeo::class, ['template' => 'course']);

    expect(array_map(fn (LayoutTab $tab): string => $tab->key, $layout()->tabs()))->toBe(['content']);
});

/** SEO fields added the way they were before the SEO tab: in Content, main column. */
function oldSeoLayout(): void
{
    lynx()->run(InstallSeo::class);
    lynx()->templates->update('course', ['add' => SeoSchema::specs(SeoSchema::pageFields())]);
}

it('moves SEO fields that sit where they used to be added', function () use ($layout, $names): void {
    oldSeoLayout();

    lynx()->run(EnableSeo::class, ['template' => 'course']);
    $seo = $layout();

    expect($seo->tabOf(SeoSchema::TITLE))->toBe('seo')
        ->and($names($seo, TemplateLayout::SIDEBAR))->toBe([SeoSchema::NOINDEX])
        ->and(lynx()->templates->fields('course')->get(SeoSchema::TITLE)?->settings['description'] ?? null)->not->toBeEmpty();
});

it('leaves SEO fields an editor placed elsewhere', function () use ($layout): void {
    oldSeoLayout();
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'extra', 'label' => 'Extra']],
        'add' => [['name' => SeoSchema::TITLE, 'tab' => 'extra'], ['name' => SeoSchema::DESCRIPTION, 'column' => 'sidebar']],
    ]);

    lynx()->run(EnableSeo::class, ['template' => 'course']);
    $seo = $layout();

    expect($seo->tabOf(SeoSchema::TITLE))->toBe('extra')
        ->and($seo->tabOf(SeoSchema::DESCRIPTION))->toBe('content')
        ->and($seo->tabOf(SeoSchema::CANONICAL))->toBe('seo');
});

it('moves the SEO fields of existing templates when migrating', function () use ($layout): void {
    oldSeoLayout();

    (require dirname(__DIR__, 3).'/packages/seo/database/migrations/2026_10_26_000000_move_seo_fields_to_their_tab.php')->up();

    expect($layout()->tabOf(SeoSchema::TITLE))->toBe('seo');
});
