<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1]);
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('note', 'text');
    lynx()->fields->add('rating', 'decimal');
    lynx()->fields->add('level', 'choice', settings: ['options' => [['value' => 'basic', 'label' => 'Basic']]]);
    lynx()->fields->add('formats', 'choice', settings: ['multiple' => true, 'options' => [['value' => 'online', 'label' => 'Online']]]);
});

it('types every field as its type presents it, and accepts any write', function (): void {
    $path = sys_get_temp_dir().'/lynx-stub-'.uniqid().'/Page.stub';

    $this->artisan('lynx:phpstan-stub', ['--path' => $path])->assertSuccessful();
    $stub = File::get($path);
    File::deleteDirectory(dirname($path));

    expect($stub)
        ->toContain('class Page implements \JsonSerializable')
        ->toContain('@property-read \LynxCms\Core\Pages\Page|null $parent')
        ->toContain('@property-read \LynxCms\Core\Pages\TranslatableValue $title')
        ->toContain('@property-read int|null $price')
        ->toContain('@property-read \LynxCms\Core\Pages\PageArray $related')
        ->toContain('@property-read \Carbon\CarbonImmutable|null $starts_on')
        ->toContain('@property-read \LynxCms\Media\Values\Pageimage|null $poster')
        ->toContain('@property-read \LynxCms\Media\Values\MediaList $gallery')
        ->toContain('@property-read \LynxCms\Core\Pages\SharedValue $note')
        ->toContain('@property-read float|null $rating')
        ->toContain('@property-read \\LynxCms\\Core\\Fields\\Choices\\Choice|null $level')
        ->toContain('@property-read \\LynxCms\\Core\\Fields\\Choices\\ChoiceList $formats')
        ->toContain('@property-write mixed $starts_on');
});
