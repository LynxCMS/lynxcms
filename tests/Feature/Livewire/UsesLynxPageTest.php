<?php

declare(strict_types=1);

use Livewire\Livewire;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Tests\Fixtures\LynxPageComponent;

/*
 * App Livewire components on Lynx pages (docs: Views › Livewire components). Livewire updates
 * arrive without the page's language prefix, so the trait keeps the page and the language.
 */

beforeEach(function (): void {
    courseSite();
    $course = createCourses('alpha')['alpha'];
    $course->setLanguageValue('pt', 'title', 'Alfa');
    $course->save();
    $this->courseId = (int) $course->id;
});

it('reads the page it was given', function (): void {
    Livewire::test(LynxPageComponent::class, ['pageId' => $this->courseId])
        ->assertSee('Title: Alpha');
});

it('keeps the visitor language on later updates', function (): void {
    $component = lynx()->language('pt', fn () => Livewire::test(LynxPageComponent::class, ['pageId' => $this->courseId]));

    // An update request starts in the default language, as Livewire's own URL has no prefix.
    app(LanguageContext::class)->set('default');

    $component->call('$refresh')->assertSee('Title: Alfa');
});

it('refuses to let visitors change the page', function (): void {
    expect(fn () => Livewire::test(LynxPageComponent::class, ['pageId' => $this->courseId])->set('pageId', 1))
        ->toThrow(Exception::class);
});
