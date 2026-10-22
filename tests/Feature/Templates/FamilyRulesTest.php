<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    lynx()->templates->add('lesson', fields: [['name' => 'title', 'required' => true]]);
});

function pageOn(string $template, string $parent, string $name): Page
{
    $page = lynx()->pages->new($template, $parent);
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->save();

    return $page;
}

it('allows any child and parent template by default', function (): void {
    expect(pageOn('lesson', '/courses/', 'intro')->id)->toBeInt();
});

it('refuses children under a template whose pages may not have any', function (): void {
    lynx()->templates->update('course', ['allow_children' => false]);
    createCourses('alpha');

    expect(fn (): Page => pageOn('lesson', '/courses/alpha/', 'intro'))->toThrow(ValidationException::class, 'Pages using [course] cannot have children.');
});

it('limits the templates of children', function (): void {
    lynx()->templates->update('courses', ['child_templates' => ['course']]);

    expect(fn (): Page => pageOn('lesson', '/courses/', 'intro'))->toThrow(ValidationException::class, 'Under [courses] pages may only use: course.')
        ->and(createCourses('alpha')['alpha']->id)->toBeInt();
});

it('limits the templates of parents', function (): void {
    lynx()->templates->update('lesson', ['parent_templates' => ['course']]);
    createCourses('alpha');

    expect(fn (): Page => pageOn('lesson', '/courses/', 'intro'))->toThrow(ValidationException::class, 'Pages using [lesson] may only be placed under: course.')
        ->and(pageOn('lesson', '/courses/alpha/', 'intro')->id)->toBeInt();
});

it('applies the rules when a page moves or switches template', function (): void {
    lynx()->templates->update('courses', ['child_templates' => ['course']]);
    createCourses('alpha');
    $intro = pageOn('lesson', '/courses/alpha/', 'intro');

    expect(fn (): mixed => lynx()->pages->move($intro, '/courses/'))->toThrow(ValidationException::class, 'Under [courses]')
        ->and(fn (): mixed => lynx()->pages->switchTemplate('/courses/alpha/', 'lesson'))->toThrow(ValidationException::class, 'Under [courses]');
});

it('reports the rules to agents and keeps them in schema export', function (): void {
    lynx()->templates->update('courses', ['child_templates' => ['course'], 'allow_children' => true]);
    lynx()->templates->update('lesson', ['parent_templates' => ['course']]);

    $courses = lynx()->templates->get('courses');
    expect([$courses->allowsChildren(), $courses->childTemplates(), lynx()->templates->get('lesson')->parentTemplates()])
        ->toBe([true, ['course'], ['course']]);
});

it('refuses template names it cannot read', function (): void {
    expect(fn () => lynx()->templates->update('courses', ['child_templates' => ['Not A Name']]))->toThrow(ValidationException::class);
});
