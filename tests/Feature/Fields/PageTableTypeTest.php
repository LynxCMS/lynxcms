<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts', 'time');
    lynx()->templates->add('session', fields: [['name' => 'title', 'required' => true], 'starts']);
    lynx()->fields->add('sessions', 'page_table', settings: ['templates' => ['session'], 'columns' => ['title', 'starts']]);
    lynx()->templates->update('course', ['add' => ['sessions']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alpha = $alpha;
});

function courseSession(Page $course, string $title, string $starts): Page
{
    return lynx()->run(SavePage::class, ['template' => 'session', 'parent' => $course->id, 'values' => ['title' => ['default' => $title], 'starts' => $starts], 'status' => ['hidden']])->valueAs(Page::class);
}

it('holds pages in the order chosen and gives templates a PageArray', function (): void {
    $late = courseSession($this->alpha, 'Afternoon', '14:00');
    $early = courseSession($this->alpha, 'Morning', '09:00');
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['sessions' => [$early->id, $late->id]]]);

    $sessions = lynx()->pages->get($this->alpha->id)->sessions;

    expect($sessions)->toBeInstanceOf(PageArray::class)
        ->and($sessions->map(fn (Page $page): string => (string) $page->title))->toBe(['Morning', 'Afternoon'])
        ->and(lynx()->pages->find('template=course, sessions='.$late->id)->map(fn (Page $page): string => $page->name()))->toBe(['alpha']);
});

it('refuses pages of templates the field does not allow', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['sessions' => [lynx()->pages->get('/courses/')->id]]]);
})->throws(ValidationException::class, 'session');

it('needs at least one template', function (): void {
    lynx()->fields->add('items', 'page_table');
})->throws(ValidationException::class);

it('describes itself to agents with its templates and columns', function (): void {
    $type = lynx()->fields->find('sessions')->fieldType();

    expect($type::description())->toContain('templates')
        ->and(lynx()->fields->find('sessions')->config())->toMatchArray(['templates' => ['session'], 'columns' => ['title', 'starts']]);
});
