<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Fields\Choices\ChoiceList;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\SharedValue;
use LynxCms\Core\Pages\TranslatableValue;
use LynxCms\Media\Values\MediaList;
use Symfony\Component\Process\Process;

/*
 * lynx:phpstan writes every field's type from the schema, and Lynx's PHPStan extension reads it
 * (ADR-0083), so $page->field and $item->field are checked like typed properties.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('starts_on', 'date');
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1]);
    lynx()->fields->add('gallery', 'images');
    lynx()->fields->add('note', 'text');
    lynx()->fields->add('rating', 'decimal');
    lynx()->fields->add('level', 'choice', settings: ['options' => [['value' => 'basic', 'label' => 'Basic']]]);
    lynx()->fields->add('formats', 'choice', settings: ['multiple' => true, 'options' => [['value' => 'online', 'label' => 'Online']]]);
    $this->folder = testingPath('phpstan-types');
    File::deleteDirectory($this->folder);
    File::ensureDirectoryExists($this->folder);
});

afterEach(fn () => File::deleteDirectory($this->folder));

it('writes every field as its type presents it, fully qualified', function (): void {
    $this->artisan('lynx:phpstan', ['--path' => $this->folder.'/types.php'])->assertSuccessful();
    $types = require $this->folder.'/types.php';

    expect(array_map(fn (array $property): string => $property['type'], $types['page']))->toMatchArray([
        'name' => 'string',
        'parent' => '\LynxCms\Core\Pages\Page|null',
        'children' => '\\'.PageArray::class,
        'template' => '\\'.Template::class,
        'title' => '\\'.TranslatableValue::class,
        'price' => 'int|null',
        'related' => '\\'.PageArray::class,
        'starts_on' => '\Carbon\CarbonImmutable|null',
        'poster' => '\LynxCms\Media\Values\Pageimage|null',
        'gallery' => '\\'.MediaList::class,
        'note' => '\\'.SharedValue::class,
        'rating' => 'float|null',
        'level' => '\LynxCms\Core\Fields\Choices\Choice|null',
        'formats' => '\\'.ChoiceList::class,
    ])
        ->and($types['page']['name']['writable'])->toBeTrue()
        ->and($types['page']['url']['writable'])->toBeFalse()
        ->and($types['page']['price']['writable'])->toBeTrue()
        ->and($types['item']['key']['type'])->toBe('string')
        ->and($types['item']['note']['type'])->toBe('\\'.SharedValue::class);
});

it('types pages and items in PHPStan itself', function (): void {
    $this->artisan('lynx:phpstan', ['--path' => $this->folder.'/types.php'])->assertSuccessful();
    File::put($this->folder.'/Checked.php', <<<'PHP_WRAP'
    <?php

    use LynxCms\Core\Pages\Items\Item;
    use LynxCms\Core\Pages\Page;

    function checked(Page $page, Item $item): void
    {
        \PHPStan\dumpType($page->price);
        \PHPStan\dumpType($page->starts_on);
        \PHPStan\dumpType($item->note);
        \PHPStan\dumpType($item->key);
        $page->name = 'renamed';
        $page->price = '12';
        $page->url = '/elsewhere';
        echo $page->nonexistent;
    }
    PHP_WRAP);
    $extension = dirname(__DIR__, 3).'/packages/core/phpstan/extension.neon';
    File::put($this->folder.'/phpstan.neon', "includes:\n    - {$extension}\n\nparameters:\n    level: max\n    tmpDir: {$this->folder}/tmp\n    lynx:\n        types: {$this->folder}/types.php\n");

    $process = new Process([PHP_BINARY, dirname(__DIR__, 3).'/vendor/bin/phpstan', 'analyse', '-c', $this->folder.'/phpstan.neon', '--error-format=json', '--no-progress', $this->folder.'/Checked.php']);
    $process->setTimeout(120)->run();
    $messages = array_column(json_decode($process->getOutput(), true)['files'][$this->folder.'/Checked.php']['messages'] ?? [], 'message');

    expect($messages)->toContain('Dumped type: int|null')
        ->toContain('Dumped type: Carbon\CarbonImmutable|null')
        ->toContain('Dumped type: LynxCms\Core\Pages\SharedValue')
        ->toContain('Dumped type: string')
        ->and(implode("\n", $messages))->toContain('LynxCms\Core\Pages\Page::$url is not writable')
        ->toContain('undefined property LynxCms\Core\Pages\Page::$nonexistent')
        ->not->toContain('$name')
        ->not->toContain('$price');
});
