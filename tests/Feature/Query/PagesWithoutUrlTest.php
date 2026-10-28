<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/*
 * Pages without a URL (slides, promos, settings, forms) show inside other pages, never on their own,
 * so a selector leaves them out unless it asks for them (ADR-0096): by naming their template, by
 * reading below such a page, by id, or with include=no_url or include=all.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->templates->add('slides', fields: [['name' => 'title', 'required' => true]], hasUrl: false);
    lynx()->templates->add('slide', fields: [['name' => 'title', 'required' => true, 'searchable' => true]], hasUrl: false);
    $this->slides = urlLessPage('slides', '/', 'hero', 'Hero');
    $this->slide = urlLessPage('slide', $this->slides, 'launch', 'Launch of the course catalogue');
});

function urlLessPage(string $template, Page|string $parent, string $name, string $title): Page
{
    $page = lynx()->pages->new($template, $parent);
    $page->name = $name;
    $page->title = $title;
    $page->save();

    return $page;
}

/** @return list<string> */
function namesOf(PageArray $pages): array
{
    return array_map(fn (Page $page): string => $page->name(), $pages->all());
}

it('leaves pages without a URL out of a selector that does not ask for them', function (): void {
    expect(namesOf(lynx()->pages->find('parent=/')))->not->toContain('hero')
        ->and(namesOf(lynx()->pages->find('title%=launch')))->toBe([])
        ->and(namesOf(lynx()->pages->get('/')->children()))->not->toContain('hero');
});

it('leaves them out of searches', function (): void {
    expect(namesOf(lynx()->pages->find('search*="catalogue"')))->toBe([]);
});

it('finds them when the selector names their template', function (): void {
    expect(namesOf(lynx()->pages->find('template=slide')))->toBe(['launch'])
        ->and(namesOf(lynx()->pages->find('template=course|slide, sort=name')))->toBe(['alpha', 'launch'])
        ->and(namesOf(lynx()->pages->find('template!=slide, title%=launch')))->toBe([]);
});

it('finds them below a page without a URL', function (): void {
    expect(namesOf($this->slides->children()))->toBe(['launch'])
        ->and(namesOf(lynx()->pages->find('has_parent='.$this->slides->id)))->toBe(['launch'])
        ->and(namesOf(lynx()->pages->find('parent='.$this->slides->id.', title%=launch')))->toBe(['launch']);
});

it('finds them by id', function (): void {
    expect(namesOf(lynx()->pages->find('id='.$this->slide->id)))->toBe(['launch'])
        ->and(lynx()->pages->findOne('id='.$this->slide->id)?->name())->toBe('launch');
});

it('finds them with include=no_url or include=all', function (string $include): void {
    expect(namesOf(lynx()->pages->find("title%=launch, include={$include}")))->toBe(['launch']);
})->with(['no_url', 'all']);

it('still leaves out unpublished pages without a URL when only include=no_url is given', function (): void {
    lynx()->pages->setStatus($this->slide->id, add: ['unpublished']);

    expect(namesOf(lynx()->pages->find('title%=launch, include=no_url')))->toBe([])
        ->and(namesOf(lynx()->pages->find('title%=launch, include=no_url|unpublished')))->toBe(['launch']);
});
