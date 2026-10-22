<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(fn (): Lynx => courseSite());

function topLevel(string $name): Page
{
    lynx()->templates->find('topic') ?? lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('topic', '/');
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->save();

    return $page;
}

it('finds a page by its name at any depth, like ProcessWire', function (): void {
    $alpha = createCourses('alpha')['alpha'];

    expect(lynx()->pages->get('alpha')?->id)->toBe($alpha->id);
});

it('finds hidden and unpublished pages by name', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($alpha, add: ['hidden', 'unpublished']);

    expect(lynx()->pages->get('alpha')?->id)->toBe($alpha->id);
});

it('returns the first page in sort order when several share a name', function (): void {
    createCourses('alpha');
    topLevel('alpha');

    expect(lynx()->pages->get('alpha')?->id)->toBe(lynx()->pages->findOne('name=alpha, include=all')?->id)
        ->and(lynx()->pages->count('name=alpha, include=all'))->toBe(2);
});

it('matches bare names in the default language only', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    $alpha->setLanguageName('pt', 'alfa');
    $alpha->save();

    expect(lynx()->pages->get('alfa'))->toBeNull();
});

it('reads anything with a slash as a path only', function (): void {
    $alpha = createCourses('alpha')['alpha'];

    expect(lynx()->pages->get('/courses/alpha')?->id)->toBe($alpha->id)
        ->and(lynx()->pages->get('courses/alpha')?->id)->toBe($alpha->id)
        ->and(lynx()->pages->get('/alpha'))->toBeNull();
});

it('reads a bare number as an id first', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    $numbered = topLevel((string) $alpha->id);

    expect(lynx()->pages->get((string) $alpha->id)?->id)->toBe($alpha->id)
        ->and(lynx()->pages->get("/{$alpha->id}/")?->id)->toBe($numbered->id);
});

it('ignores bare values that cannot be page names', function (): void {
    createCourses('alpha');

    expect(lynx()->pages->get('Alpha Course'))->toBeNull();
});

it('resolves a bare name in actions only when one page has it', function (): void {
    $alpha = createCourses('alpha')['alpha'];

    lynx()->pages->setStatus('alpha', add: ['hidden']);
    expect(lynx()->pages->get($alpha->id)->hasStatus(PageStatus::HIDDEN))->toBeTrue();

    topLevel('alpha');
    expect(fn () => lynx()->pages->setStatus('alpha', add: ['locked']))->toThrow(ValidationException::class, 'Several pages are named [alpha]');
});

it('redirects a one-segment URL to the unique page with that name', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($alpha, add: ['unique']);

    $this->get('/alpha')->assertStatus(301)->assertHeader('Location', 'http://localhost/courses/alpha');
});

it('does not redirect to pages that are not unique, or not published', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    lynx()->pages->setStatus($beta, add: ['unique', 'unpublished']);

    $this->get('/alpha')->assertNotFound();
    $this->get('/beta')->assertNotFound();
    $this->get('/pt/alpha')->assertNotFound();
});
