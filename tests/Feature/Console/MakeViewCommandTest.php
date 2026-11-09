<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * lynx:make:view writes a starting view for a template: each field printed the way its type
 * reads, in the template's order. The view it writes renders the template's pages.
 */

beforeEach(function (): void {
    courseSite();
    $this->views = testingPath('make-view');
    File::deleteDirectory($this->views);
    File::ensureDirectoryExists($this->views);
    config(['view.paths' => [$this->views]]);
    app('view')->getFinder()->setPaths([$this->views]);
});

afterEach(function (): void {
    File::deleteDirectory($this->views);
});

function madeView(string $template): string
{
    return File::get(test()->views.'/templates/'.$template.'.blade.php');
}

it('writes a view that prints each field the way its type reads', function (): void {
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('featured', 'toggle');
    lynx()->fields->add('level', 'choice', settings: ['options' => [['value' => 'basic', 'label' => 'Basic']]]);
    lynx()->fields->add('website', 'url');
    lynx()->fields->add('contact', 'email');
    lynx()->fields->add('mentor', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('caption', 'text');
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'caption'], ['name' => 'mentor']]]);
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [['slug' => 'quote', 'fields' => [['name' => 'caption']]]]]);
    lynx()->fields->add('secret', 'password');
    lynx()->templates->update('course', ['add' => ['starts_on', 'featured', 'level', 'website', 'contact', 'mentor', 'credits', 'blocks', 'secret']]);

    $this->artisan('lynx:make:view', ['template' => 'course'])->assertSuccessful();
    $view = madeView('course');

    expect($view)->toContain('@field($page->title)', '@field($page->body)', '$page->starts_on?->isoFormat(', '@foreach ($page->related as $related)', '$page->mentor->url', '@foreach ($page->credits as $credit)', '@field($credit->caption)', '@matrix($page->blocks)')
        ->not->toContain('$page->secret');
});

it('writes a view that renders the template\'s pages', function (): void {
    // A template the workbench app has no view for, so the written one renders.
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('caption', 'text');
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'caption']]]);
    lynx()->templates->add('workshop', fields: ['title', 'summary', 'body', 'price', 'related', 'starts_on', 'credits']);
    createCourses('beta');
    $workshop = lynx()->pages->new('workshop', '/');
    $workshop->name = 'clay';
    $workshop->title = 'Clay';
    $workshop->summary = 'An intro';
    $workshop->body = '<p>Body</p>';
    $workshop->price = 120;
    $workshop->starts_on = '2026-12-01';
    $workshop->related = [(int) lynx()->pages->get('/courses/beta/')->id];
    $workshop->credits = [['values' => ['caption' => 'Photo']]];
    $workshop->save();

    $this->artisan('lynx:make:view', ['template' => 'workshop'])->assertSuccessful();

    $this->get('/clay')->assertOk()->assertSee('<main>', escape: false)->assertSee('An intro')->assertSee('Photo')->assertSee('/courses/beta');
});

it('keeps a view that exists unless forced', function (): void {
    File::ensureDirectoryExists($this->views.'/templates');
    File::put($this->views.'/templates/course.blade.php', 'Mine');

    $this->artisan('lynx:make:view', ['template' => 'course'])->assertFailed();
    expect(madeView('course'))->toBe('Mine');

    $this->artisan('lynx:make:view', ['template' => 'course', '--force' => true])->assertSuccessful();
    expect(madeView('course'))->toContain('@field($page->title)');
});

it('names the fields of other editor tabs instead of printing them, unless asked', function (): void {
    lynx()->fields->add('meta_title', 'text');
    lynx()->templates->update('course', ['add' => [['name' => 'meta_title', 'tab' => 'seo']], 'tabs' => [['key' => 'seo', 'label' => 'SEO']]]);

    $this->artisan('lynx:make:view', ['template' => 'course'])->assertSuccessful();
    expect(madeView('course'))->not->toContain('@field($page->meta_title)')->toContain('meta_title');

    $this->artisan('lynx:make:view', ['template' => 'course', '--force' => true, '--all-tabs' => true])->assertSuccessful();
    expect(madeView('course'))->toContain('@field($page->meta_title)');
});

it('wraps the view in a layout component when asked', function (): void {
    $this->artisan('lynx:make:view', ['template' => 'course', '--layout' => 'layouts.site'])->assertSuccessful();

    expect(madeView('course'))->toStartWith('<x-layouts.site :page="$page">');
});

it('writes the missing views of every template with pages', function (): void {
    lynx()->templates->add('event', fields: ['title']);
    lynx()->templates->add('venue', fields: ['title']);
    $launch = lynx()->pages->new('event', '/');
    $launch->name = 'launch';
    $launch->title = 'Launch';
    $launch->save();
    File::ensureDirectoryExists($this->views.'/templates');
    File::put($this->views.'/templates/courses.blade.php', 'Mine');

    $this->artisan('lynx:make:view', ['--all' => true])->assertSuccessful();

    expect(madeView('courses'))->toBe('Mine')
        ->and(File::exists($this->views.'/templates/event.blade.php'))->toBeTrue()
        ->and(File::exists($this->views.'/templates/venue.blade.php'))->toBeFalse();
});

it('refuses an unknown template', function (): void {
    $this->artisan('lynx:make:view', ['template' => 'nope'])->assertFailed();
});
