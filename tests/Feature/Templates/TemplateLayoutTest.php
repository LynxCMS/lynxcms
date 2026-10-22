<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Schema\SchemaExporter;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Templates\Layout\LayoutBlock;
use LynxCms\Core\Templates\Layout\LayoutTab;
use LynxCms\Core\Templates\Layout\TemplateLayout;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('seo_title', 'text', translatable: true);
    lynx()->fields->add('author', 'text');
});

/** The course template with a layout: a tab, two sections and a sidebar field. */
function layoutCourse(): void
{
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'seo', 'label' => ['default' => 'SEO', 'pt' => 'SEO']]],
        'fields' => [
            ['name' => 'title', 'required' => true],
            ['section' => 'intro', 'label' => 'Introduction', 'collapsed' => true, 'fields' => ['summary', 'body']],
            ['name' => 'author', 'column' => 'sidebar'],
            'price',
            ['section' => 'meta', 'label' => 'Search engines', 'tab' => 'seo', 'column' => 'sidebar', 'fields' => ['seo_title']],
            'related',
        ],
    ]);
}

it('keeps the field order flat, so values and selectors are unaffected', function (): void {
    layoutCourse();

    expect(lynx()->templates->fields('course')->keys()->all())->toBe(['title', 'summary', 'body', 'author', 'price', 'seo_title', 'related']);
});

it('places fields and sections by tab and column, sections at their first field', function (): void {
    layoutCourse();
    $layout = TemplateLayout::for(lynx()->templates->get('course'));
    $names = fn (string $tab, string $column): array => array_map(
        fn (LayoutBlock $block): string => $block->isSection() ? 'section:'.$block->section->key.'('.implode(',', $block->fields).')' : $block->field,
        $layout->blocks($tab, $column),
    );

    expect(array_map(fn (LayoutTab $tab): string => $tab->key, $layout->tabs()))->toBe(['content', 'seo'])
        ->and($names('content', 'main'))->toBe(['title', 'section:intro(summary,body)', 'price', 'related'])
        ->and($names('content', 'sidebar'))->toBe(['author'])
        ->and($names('seo', 'sidebar'))->toBe(['section:meta(seo_title)'])
        ->and($layout->section('intro')?->collapsed)->toBeTrue()
        ->and($layout->tabOf('seo_title'))->toBe('seo');
});

it('lays out a template without layout settings as one main column in Content', function (): void {
    $layout = TemplateLayout::for(lynx()->templates->get('course'));

    expect($layout->hasLayout())->toBeFalse()
        ->and(array_map(fn (LayoutTab $tab): string => $tab->key, $layout->tabs()))->toBe(['content'])
        ->and(array_map(fn (LayoutBlock $block): ?string => $block->field, $layout->blocks('content', 'main')))->toBe(['title', 'summary', 'body', 'price', 'related']);
});

it('refuses placements the template does not define', function (array $fields, string $message): void {
    try {
        lynx()->templates->update('course', ['fields' => $fields]);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect(implode(' ', array_merge(...array_values($exception->errors()))))->toContain($message);
    }
})->with([
    'unknown tab' => [[['name' => 'title', 'tab' => 'nope']], 'tab [nope]'],
    'reserved tab' => [[['name' => 'title', 'tab' => 'settings']], 'tab [settings]'],
    'bad column' => [[['name' => 'title', 'column' => 'left']], 'column'],
    'field placed inside a section' => [[['section' => 's', 'label' => 'S', 'fields' => [['name' => 'title', 'column' => 'sidebar']]]], 'set the tab and column on the section'],
    'duplicate section' => [[['section' => 's', 'label' => 'S', 'fields' => ['title']], ['section' => 's', 'label' => 'S', 'fields' => ['body']]], 'section [s]'],
    'nested section' => [[['section' => 's', 'label' => 'S', 'fields' => [['section' => 't', 'label' => 'T', 'fields' => ['title']]]]], 'inside sections'],
]);

it('refuses tab keys that clash with the editor tabs', function (): void {
    lynx()->templates->update('course', ['tabs' => [['key' => 'texts', 'label' => 'Texts']]]);
})->throws(ValidationException::class);

it('exports sections nested in the field list and applies them back', function (): void {
    layoutCourse();
    $data = app(SchemaExporter::class)->templateData(lynx()->templates->get('course'));

    expect($data['fields'][1])->toBe(['section' => 'intro', 'label' => 'Introduction', 'collapsed' => true, 'fields' => ['summary', 'body']])
        ->and($data['settings'])->not->toHaveKey('sections')
        ->and($data['settings']['tabs'])->toBe([['key' => 'seo', 'label' => ['default' => 'SEO', 'pt' => 'SEO']]]);
});

it('restores a layout from schema files, and applying twice changes nothing', function (): void {
    layoutCourse();
    $dir = sys_get_temp_dir().'/lynx-layout-'.uniqid();
    $sync = app(SchemaSync::class);
    $sync->export($dir);
    lynx()->templates->update('course', ['tabs' => [], 'fields' => ['title', 'summary', 'body', 'author', 'price', 'seo_title', 'related']]);

    $result = $sync->apply($dir);
    $layout = TemplateLayout::for(lynx()->templates->get('course'));
    $again = $sync->apply($dir);
    File::deleteDirectory($dir);

    expect($result->errors)->toBe([])
        ->and($layout->section('meta')?->tab)->toBe('seo')
        ->and($layout->tabOf('seo_title'))->toBe('seo')
        ->and($again->isEmpty())->toBeTrue();
});
