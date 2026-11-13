<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Finder\Search\FinderQuery;
use LynxCms\Finder\SelectorWriter;

/*
 * A field condition only finds pages whose template has the field: "is not", "is blank" and "is
 * off" would otherwise match every page of other templates, which hold no value at all.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('level', 'choice', settings: ['options' => [['value' => 'basic', 'label' => 'Basic'], ['value' => 'advanced', 'label' => 'Advanced']]]);
    lynx()->fields->add('featured', 'toggle');
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => [['name' => 'level'], ['name' => 'featured'], ['name' => 'gallery']]]);
    $courses = createCourses('alpha', 'beta');
    $courses['alpha']->level = 'advanced';
    $courses['alpha']->save();
});

/**
 * The templates of the pages a condition finds, across the whole site.
 *
 * @param  array<string, mixed>  $condition
 * @return list<string>
 */
function presenceTemplates(array $condition): array
{
    $terms = app(SelectorWriter::class)->terms(FinderQuery::fromArray(['groups' => [['conditions' => [[...['value' => '', 'to' => '', 'pages' => []], ...$condition]]]]]));

    return array_values(array_unique(lynx()->pages->find($terms)->map(fn (Page $page): string => (string) $page->template()->name)));
}

it('finds only pages whose template has the field', function (array $condition): void {
    expect(presenceTemplates($condition))->toBe(['course']);
})->with([
    'choice is not' => [['subject' => 'level', 'operator' => 'not', 'value' => 'advanced']],
    'choice is blank' => [['subject' => 'level', 'operator' => 'blank']],
    'text is blank' => [['subject' => 'summary', 'operator' => 'blank']],
    'number is not' => [['subject' => 'price', 'operator' => 'not', 'value' => '10']],
    'switch is off' => [['subject' => 'featured', 'operator' => 'off']],
    'page reference is blank' => [['subject' => 'related', 'operator' => 'blank']],
    'items are blank' => [['subject' => 'gallery', 'operator' => 'blank']],
]);

it('also keeps an "any" group to pages whose template has the field', function (): void {
    $terms = app(SelectorWriter::class)->terms(FinderQuery::fromArray(['groups' => [['match' => 'any', 'conditions' => [
        ['subject' => 'level', 'operator' => 'not', 'value' => 'advanced'],
        ['subject' => 'template', 'operator' => 'is', 'value' => 'courses'],
    ]]]]));
    $templates = array_values(array_unique(lynx()->pages->find($terms)->map(fn (Page $page): string => (string) $page->template()->name)));
    sort($templates);

    expect($templates)->toBe(['course', 'courses']);
});
