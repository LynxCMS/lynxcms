<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->fields->add('quote', 'text', translatable: true);
    lynx()->fields->add('video_url', 'text');
    lynx()->fields->add('blocks', 'matrix', label: ['default' => 'Content', 'pt' => 'Conteúdo'], settings: ['types' => [
        ['slug' => 'text', 'label' => ['default' => 'Text', 'pt' => 'Texto'], 'fields' => [['name' => 'body']]],
        ['slug' => 'video', 'label' => 'Video', 'fields' => [['name' => 'video_url', 'required' => true], ['name' => 'summary']]],
        ['slug' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'quote', 'required' => true]]],
    ]]);
    lynx()->templates->update('course', ['add' => ['blocks']]);
    ['alpha' => $this->alpha] = createCourses('alpha');
});

function blocks(array $blocks): Page
{
    lynx()->run(SavePage::class, ['id' => test()->alpha->id, 'values' => ['blocks' => $blocks]]);

    return lynx()->pages->get('/courses/alpha/');
}

it('stores items of different types with their own fields', function (): void {
    $page = blocks([
        ['type' => 'text', 'values' => ['body' => ['default' => '<p>Hello</p>', 'pt' => '<p>Olá</p>']]],
        ['type' => 'video', 'values' => ['video_url' => 'https://vimeo.com/1']],
    ]);

    expect(array_map(fn ($block) => $block->type(), $page->blocks->all()))->toBe(['text', 'video'])
        ->and((string) $page->blocks->first()?->body)->toBe('<p>Hello</p>')
        ->and((string) $page->blocks->all()[1]->video_url)->toBe('https://vimeo.com/1')
        ->and($page->blocks->all()[1]->body)->toBeNull();
});

it('needs a known type for every item', function (): void {
    try {
        blocks([['values' => []], ['type' => 'gallery', 'values' => []]]);
        $this->fail('No validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['values.blocks.0.type', 'values.blocks.1.type']);
    }
});

it('checks required fields of the item\'s own type only', function (): void {
    try {
        blocks([['type' => 'quote', 'values' => ['video_url' => 'x']]]);
        $this->fail('No validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('values.blocks.0.quote')->not->toHaveKey('values.blocks.0.video_url');
    }
});

it('keeps the values of a former type when an item changes type', function (): void {
    $items = blocks([['type' => 'video', 'values' => ['video_url' => 'https://vimeo.com/1']]])->getUnformatted('blocks');
    $items[0]['type'] = 'quote';
    $items[0]['values']['quote'] = ['default' => 'Less is more'];
    $page = blocks($items);

    expect($page->getUnformatted('blocks')[0]['values'])->toBe(['video_url' => 'https://vimeo.com/1', 'quote' => ['default' => 'Less is more']])
        ->and($page->blocks->first()?->video_url)->toBeNull()
        ->and((string) $page->blocks->first()?->quote)->toBe('Less is more');
});

it('limits the types a template offers', function (): void {
    lynx()->templates->update('course', ['fields' => ['title', 'summary', 'body', 'price', 'related', ['name' => 'blocks', 'allowed_types' => ['text']]]]);

    blocks([['type' => 'quote', 'values' => ['quote' => 'No']]]);
})->throws(ValidationException::class, 'not allowed');

it('finds pages by block type', function (): void {
    blocks([['type' => 'quote', 'values' => ['quote' => 'Yes']]]);

    expect(lynx()->pages->find('blocks.type=quote')->map(fn (Page $p): string => $p->name()))->toBe(['alpha'])
        ->and(lynx()->pages->find('blocks.quote=Yes')->count())->toBe(1);
});

it('renders each block with the view of its type, the template\'s own first', function (): void {
    $page = blocks([
        ['type' => 'text', 'values' => ['body' => '<p>Hello</p>']],
        ['type' => 'quote', 'values' => ['quote' => 'Less is more']],
        ['type' => 'video', 'values' => ['video_url' => 'https://vimeo.com/1']],
        ['type' => 'text', 'enabled' => false, 'values' => ['body' => '<p>Hidden</p>']],
    ]);
    config(['app.debug' => true]);
    $html = Blade::render('@matrix($page->blocks)', ['page' => $page]);

    expect($html)->toContain('<div class="text"><p>Hello</p></div>')
        ->and($html)->toContain('<blockquote>Less is more (alpha, 2)</blockquote>')
        ->and($html)->toContain('<!-- No view for blocks block [video]')
        ->and($html)->not->toContain('Hidden');
});

it('refuses bad type definitions', function (): void {
    expect(fn () => lynx()->fields->add('bad', 'matrix', settings: ['types' => [['slug' => 'Not OK', 'fields' => [['name' => 'body']]]]]))->toThrow(ValidationException::class)
        ->and(fn () => lynx()->fields->add('twice', 'matrix', settings: ['types' => [
            ['slug' => 'a', 'fields' => [['name' => 'body']]], ['slug' => 'a', 'fields' => [['name' => 'body']]],
        ]]))->toThrow(ValidationException::class);
});

it('labels types per language', function (): void {
    expect(lynx()->language('pt', fn () => lynx()->fields->get('blocks')->fieldType()->typeLabel(lynx()->fields->get('blocks')->config(), 'text')))->toBe('Texto');
});
