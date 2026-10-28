<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/*
 * Lists keep a page that is offline in the visitor's language and link it to its default-language
 * URL (ADR-0093); PageArray::publishedIn() keeps only the pages published in a language.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma');

    foreach (['alpha' => 'alfa', 'beta' => 'beta', 'gamma' => 'gama'] as $name => $portuguese) {
        lynx()->pages->get("/courses/{$name}/")?->setLanguageName('pt', $portuguese)->save();
    }

    lynx()->pages->get('/courses/beta/')?->setLanguageName('pt', 'beta', active: false)->save();
});

function defaultNames(PageArray $pages): string
{
    return $pages->implode(', ', fn (Page $page): string => $page->name(lynx()->languages->default()));
}

it('lists a page offline in the current language, linking to its default-language URL', function (): void {
    lynx()->languages->setCurrent('pt');
    $courses = lynx()->pages->find('template=course, sort=name');

    expect(defaultNames($courses))->toBe('alpha, beta, gamma')
        ->and($courses->all()[1]->url())->toBe($courses->all()[1]->localUrl(lynx()->languages->default()));
});

it('keeps only the pages published in the current language', function (): void {
    lynx()->languages->setCurrent('pt');
    $published = lynx()->pages->find('template=course, sort=name')->publishedIn();

    expect(defaultNames($published))->toBe('alpha, gamma')
        ->and($published->total())->toBe(2);
});

it('keeps the pages published in a language it is given', function (): void {
    $courses = lynx()->pages->find('template=course, sort=name');

    expect($courses->publishedIn('pt')->count())->toBe(2)
        ->and($courses->publishedIn('default')->count())->toBe(3)
        ->and($courses->publishedIn(lynx()->languages->get('pt'))->count())->toBe(2);
});

it('keeps pages without a URL, which show in every language', function (): void {
    lynx()->templates->add('snippet', fields: [['name' => 'title', 'required' => true]], hasUrl: false);
    $snippet = lynx()->pages->new('snippet', '/');
    $snippet->name = 'footer-note';
    $snippet->title = 'Footer note';
    $snippet->save();

    expect(lynx()->pages->find('template=snippet')->publishedIn('pt')->count())->toBe(1);
});
