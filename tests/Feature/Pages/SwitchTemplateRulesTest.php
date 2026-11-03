<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Tables;

/*
 * Switching a page's template keeps to the template's own rules: the templates it may switch to,
 * singletons, and the children the new template allows. Switching to a template without a URL
 * takes the page's address away; its path stays, inactive, as paths always do.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->templates->add('lesson', fields: [['name' => 'title', 'required' => true]]);
});

/** The error keys a switch is refused with, or [] when it goes through. */
function switchRefusal(string $page, string $template): array
{
    try {
        lynx()->pages->switchTemplate($page, $template);
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('switches only to the templates the current one allows', function (): void {
    lynx()->templates->update('course', ['allowed_templates' => ['courses']]);

    expect(switchRefusal('/courses/alpha/', 'lesson'))->toBe(['template'])
        ->and(lynx()->pages->get('/courses/alpha/')?->template()->name)->toBe('course');
});

it('refuses a singleton template that already has its page', function (): void {
    lynx()->templates->add('about', fields: [['name' => 'title', 'required' => true]], singleton: true);
    $about = lynx()->pages->new('about', '/');
    $about->name = 'about';
    $about->title = 'About';
    $about->save();

    expect(switchRefusal('/courses/alpha/', 'about'))->toBe(['template']);
});

it('refuses a template that does not allow the page\'s children', function (): void {
    lynx()->templates->update('lesson', ['allow_children' => false]);

    expect(switchRefusal('/courses/', 'lesson'))->toBe(['template'])
        ->and(lynx()->pages->get('/courses/')?->template()->name)->toBe('courses');
});

it('takes the address away when the new template has no URL', function (): void {
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]], hasUrl: false);
    $id = lynx()->pages->get('/courses/alpha/')?->id;

    expect(switchRefusal('/courses/alpha/', 'note'))->toBe([])
        ->and(lynx()->pages->get((int) $id)?->template()->name)->toBe('note')
        ->and(DB::table(Tables::PAGE_PATHS)->where('page_id', $id)->where('active', true)->exists())->toBeFalse()
        ->and(DB::table(Tables::PAGE_PATHS)->where('page_id', $id)->exists())->toBeTrue();

    $this->get('/courses/alpha/')->assertNotFound();
});
