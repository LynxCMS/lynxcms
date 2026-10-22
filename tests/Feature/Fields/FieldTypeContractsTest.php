<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Tests\Fixtures\SpotlightType;

beforeEach(function (): void {
    courseSite();
    lynx()->fieldTypes()->register(SpotlightType::class);
    lynx()->fields->add('spotlight', 'spotlight');
    lynx()->templates->update('courses', ['fields' => ['title', 'spotlight']]);
    ['alpha' => $this->alpha] = createCourses('alpha', 'beta');
    $courses = lynx()->pages->get('/courses/');
    $courses->spotlight = ['note' => 'Start here', 'pages' => [$this->alpha->id]];
    $courses->save();
});

it('lets a field type present its value', function (): void {
    $spotlight = lynx()->pages->get('/courses/')?->spotlight;

    expect($spotlight->note)->toBe('Start here')
        ->and($spotlight->pages->first()?->name())->toBe('alpha')
        ->and(lynx()->pages->get('/courses/')?->getUnformatted('spotlight'))->toBe(['note' => 'Start here', 'pages' => [$this->alpha->id]]);
});

it('keeps presenting core types as before', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->related = [lynx()->pages->get('/courses/beta/')?->id];
    $alpha->save();

    expect(lynx()->pages->get('/courses/alpha/')?->related->first()?->name())->toBe('beta')
        ->and(lynx()->pages->get('/courses/alpha/')?->getUnformatted('related'))->toBe([lynx()->pages->get('/courses/beta/')?->id]);
});

it('finds backlinks through any type that references pages', function (): void {
    expect(lynx()->pages->backlinks('/courses/alpha/')->map(fn ($page): string => $page->name()))->toBe(['courses']);
});

it('eager loads the pages a type references', function (): void {
    $found = lynx()->pages->find('template=courses', with: ['spotlight']);

    expect($found->first()?->spotlight->pages->first()?->name())->toBe('alpha');
});

it('exports references as keys and imports them back for any such type', function (): void {
    $dir = sys_get_temp_dir().'/lynx-contracts-'.uniqid();
    app(ContentExporter::class)->export($dir);
    $line = collect(explode("\n", File::get($dir.'/pages.jsonl')))->filter()->map(fn (string $l): array => json_decode($l, true))->firstWhere('key', '/courses/');

    expect($line['values']['*']['spotlight'])->toBe(['note' => 'Start here', 'pages' => ['/courses/alpha/']]);

    $courses = lynx()->pages->get('/courses/');
    $courses->spotlight = ['note' => 'Changed', 'pages' => []];
    $courses->save();
    lynx()->run(ImportContent::class, ['path' => $dir]);
    File::deleteDirectory($dir);

    expect(lynx()->pages->get('/courses/')?->getUnformatted('spotlight'))->toBe(['note' => 'Start here', 'pages' => [$this->alpha->id]]);
});

it('refuses selectors on types that cannot be compared', function (): void {
    lynx()->pages->find('spotlight=x');
})->throws(SelectorException::class);
