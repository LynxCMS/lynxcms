<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * Text and plain textarea inputs with a "max_length" show a character counter, "48/60", so editors
 * see the limit before saving. Rich text and fields without a limit show none.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->fields->add('tagline', 'text', settings: ['max_length' => 60]);
    lynx()->fields->add('teaser', 'textarea', settings: ['max_length' => 160]);
    lynx()->templates->update('course', ['add' => ['tagline', 'teaser']]);
    createCourses('alpha');
});

function courseEditor(): Testable
{
    return Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/alpha/')->id]);
}

it('counts characters against the limit of text and plain textarea fields', function (): void {
    courseEditor()
        ->assertSeeHtml('data-char-counter="tagline"')
        ->assertSeeHtml('data-char-counter="teaser"')
        ->assertSeeHtml('data-char-max="60"')
        ->assertSeeHtml('data-char-max="160"');
});

it('shows no counter without a limit or for rich text', function (): void {
    lynx()->fields->update('body', ['settings' => ['max_length' => 500]]);

    courseEditor()
        ->assertDontSeeHtml('data-char-counter="summary"')
        ->assertDontSeeHtml('data-char-counter="body"');
});
