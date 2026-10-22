<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
});

function topic(string $name, string $parent = '/'): Page
{
    $page = lynx()->pages->new('topic', $parent);
    $page->name = $name;
    $page->title = ucfirst($name);
    $page->save();

    return lynx()->pages->get($page->id);
}

/** @param  callable(): mixed  $write */
function refusedWith(callable $write, string $key, string $message): void
{
    try {
        $write();
        test()->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key)
            ->and(implode(' ', $exception->errors()[$key]))->toContain($message);
    }
}

it('marks a page unique when no other page has its name', function (): void {
    $alpha = createCourses('alpha')['alpha'];

    lynx()->pages->setStatus($alpha, add: ['unique']);

    expect(lynx()->pages->get($alpha->id)->hasStatus(PageStatus::UNIQUE))->toBeTrue();
});

it('refuses unique when another page anywhere has the same name', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    topic('alpha');

    refusedWith(fn (): Page => lynx()->pages->setStatus($alpha, add: ['unique']), 'add', 'already used by /alpha/');
});

it('ignores names in other languages', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    $other = topic('other');
    $other->setLanguageName('pt', 'alpha');
    $other->save();

    lynx()->pages->setStatus($alpha, add: ['unique']);

    expect(lynx()->pages->get($alpha->id)->hasStatus(PageStatus::UNIQUE))->toBeTrue();
});

it('refuses a new page that takes a unique page\'s name, anywhere', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($alpha, add: ['unique']);

    refusedWith(fn (): Page => topic('alpha'), 'names.default', 'unique page /courses/alpha/');
});

it('refuses renaming a page to a unique page\'s name', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    lynx()->pages->setStatus($alpha, add: ['unique']);
    $beta->name = 'alpha';

    refusedWith(fn (): mixed => $beta->save(), 'names.default', 'unique page /courses/alpha/');
});

it('refuses renaming a unique page to a name in use', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    topic('taken');
    lynx()->pages->setStatus($alpha, add: ['unique']);
    $alpha = lynx()->pages->get($alpha->id);
    $alpha->name = 'taken';

    refusedWith(fn (): mixed => $alpha->save(), 'names.default', 'already used by /taken/');
});

it('checks a save that sets unique through its status', function (): void {
    createCourses('alpha');

    refusedWith(fn (): mixed => lynx()->run(SavePage::class, ['template' => 'topic', 'parent' => '/', 'names' => ['default' => ['name' => 'alpha']], 'values' => ['title' => 'A'], 'status' => ['unique']]), 'names.default', 'already used by /courses/alpha/');
});

it('lets a unique page keep its own name and be saved again', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($alpha, add: ['unique']);
    $alpha = lynx()->pages->get($alpha->id);
    $alpha->title = 'Alpha again';
    $alpha->save();
    lynx()->pages->move($alpha, '/');

    expect(lynx()->pages->get($alpha->id)->path())->toBe('alpha');
});

it('frees the name once unique is removed', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    lynx()->pages->setStatus($alpha, add: ['unique']);
    lynx()->pages->setStatus($alpha, remove: ['unique']);

    expect(topic('alpha')->path())->toBe('alpha');
});
