<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Kind;
use LynxCms\Finder\Catalog\Operator;
use LynxCms\Finder\Search\FinderQuery;
use LynxCms\Finder\SelectorWriter;
use LynxCms\Media\Actions\StoreMedia;

/*
 * Fields that hold items (images, files, repeaters, matrices) are found by whether they hold any,
 * or how many: blank, not blank and "has at least", counted as selectors count items.
 */

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => ['gallery']]);
    $this->courses = createCourses('alpha', 'beta');
    $alpha = $this->courses['alpha'];
    $image = lynx()->run(StoreMedia::class, ['page' => $alpha->id, 'field' => 'gallery', 'base64' => base64_encode(jpeg(400, 300)), 'filename' => 'still.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => ['gallery' => [['values' => $image]]]]);
});

/**
 * @return list<string>
 */
function itemNames(string $operator, string $value = ''): array
{
    $query = FinderQuery::fromArray(['groups' => [['conditions' => [['subject' => 'gallery', 'operator' => $operator, 'value' => $value]]]]]);
    $names = lynx()->pages->find('template=course, '.app(SelectorWriter::class)->terms($query))->map(fn (Page $page): string => $page->name());
    sort($names);

    return $names;
}

it('offers image fields as items, with blank, not blank and a count', function (): void {
    $subject = app(ConditionCatalog::class)->find('gallery');

    expect($subject?->kind)->toBe(Kind::Items)
        ->and(array_map(fn (Operator $operator): string => $operator->value, Kind::Items->operators()))->toBe(['filled', 'blank', 'at_least']);
});

it('finds pages with images and pages without', function (): void {
    expect(itemNames('filled'))->toBe(['alpha'])
        ->and(itemNames('blank'))->toBe(['beta'])
        ->and(itemNames('at_least', '2'))->toBe([]);
});

it('counts the items to write the selector', function (): void {
    $query = FinderQuery::fromArray(['groups' => [['match' => 'any', 'conditions' => [['subject' => 'gallery', 'operator' => 'blank'], ['subject' => 'gallery', 'operator' => 'at_least', 'value' => '3']]]]]);

    expect(app(SelectorWriter::class)->terms($query))->toBe('(gallery.count=0, template=course), (gallery.count>=3)');
});
