<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;

/*
 * A page field's "templates" holds from every entry point, not only the admin's picker (ADR-0095):
 * a page being added must use one of them. Pages a field already holds stay, so narrowing the
 * setting never blocks an unrelated save.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma');
});

function pathId(string $path): int
{
    return (int) lynx()->pages->get($path)?->id;
}

function saveRelatedOf(string $path, array $ids): void
{
    $page = lynx()->pages->get($path) ?? throw new RuntimeException("No [{$path}].");
    $page->related = $ids;
    $page->save();
}

it('refuses a page of another template, from code', function (): void {
    lynx()->fields->update('related', ['settings' => ['templates' => ['course']]]);

    expect(fn () => saveRelatedOf('/courses/alpha/', [pathId('/courses/')]))->toThrow(ValidationException::class, 'course');

    saveRelatedOf('/courses/alpha/', [pathId('/courses/beta/')]);
    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(1);
});

it('refuses it through the action, as MCP and the agent save, and on a dry run', function (): void {
    lynx()->fields->update('related', ['settings' => ['templates' => ['course']]]);

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => pathId('/courses/alpha/'), 'values' => ['related' => [pathId('/')]]], dryRun: true))
        ->toThrow(ValidationException::class, 'course');
});

it('keeps pages the field held before its templates were narrowed', function (): void {
    saveRelatedOf('/courses/alpha/', [pathId('/courses/')]);
    lynx()->fields->update('related', ['settings' => ['templates' => ['course']]]);

    $page = lynx()->pages->get('/courses/alpha/');
    $page->title = 'Alpha, renamed';
    $page->save();
    saveRelatedOf('/courses/alpha/', [pathId('/courses/'), pathId('/courses/beta/')]);

    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(2)
        ->and(fn () => saveRelatedOf('/courses/alpha/', [pathId('/courses/'), pathId('/')]))->toThrow(ValidationException::class, 'course');
});

it('takes the templates a template sets for its own use', function (): void {
    lynx()->templates->add('landing', fields: [['name' => 'title', 'required' => true], ['name' => 'related', 'templates' => ['course']]]);
    $landing = lynx()->pages->new('landing', '/');
    $landing->name = 'landing';
    $landing->title = 'Landing';
    $landing->related = [pathId('/')];

    expect(fn () => $landing->save())->toThrow(ValidationException::class, 'course');
});

it('takes the templates a block type sets, and keeps what a block already held', function (): void {
    lynx()->fields->add('sections', 'matrix', settings: ['types' => [
        ['slug' => 'courses', 'label' => 'Courses', 'fields' => [['name' => 'related', 'templates' => ['course']]]],
        ['slug' => 'any', 'label' => 'Any pages', 'fields' => [['name' => 'related']]],
    ]]);
    lynx()->templates->update('course', ['add' => ['sections']]);
    $page = lynx()->pages->get('/courses/alpha/');
    $page->sections = [['type' => 'any', 'values' => ['related' => [pathId('/')]]]];
    $page->save();

    $page = lynx()->pages->get('/courses/alpha/');
    $page->sections = [['type' => 'courses', 'values' => ['related' => [pathId('/courses/')]]]];
    expect(fn () => $page->save())->toThrow(ValidationException::class, 'course');

    $page = lynx()->pages->get('/courses/alpha/');
    $kept = (array) $page->getUnformatted('sections');
    $kept[] = ['type' => 'courses', 'values' => ['related' => [pathId('/courses/beta/')]]];
    $page->sections = $kept;
    $page->save();

    expect(collect(lynx()->pages->get('/courses/alpha/')?->getUnformatted('sections'))->count())->toBe(2);
});

it('treats a page table the same way: new items only', function (): void {
    lynx()->fields->add('sessions', 'page_table', settings: ['templates' => ['course']]);
    lynx()->templates->update('course', ['add' => ['sessions']]);
    lynx()->run(SavePage::class, ['id' => pathId('/courses/alpha/'), 'values' => ['sessions' => [pathId('/courses/beta/')]]]);
    lynx()->fields->update('sessions', ['settings' => ['templates' => ['home']]]);

    lynx()->run(SavePage::class, ['id' => pathId('/courses/alpha/'), 'values' => ['sessions' => [pathId('/courses/beta/'), pathId('/')]]]);

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => pathId('/courses/alpha/'), 'values' => ['sessions' => [pathId('/courses/beta/'), pathId('/'), pathId('/courses/gamma/')]]]))
        ->toThrow(ValidationException::class, 'home');
});

it('takes any page when no templates are set', function (): void {
    saveRelatedOf('/courses/alpha/', [pathId('/'), pathId('/courses/')]);

    expect(lynx()->pages->get('/courses/alpha/')?->related->count())->toBe(2);
});
