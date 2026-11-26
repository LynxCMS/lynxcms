<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use LynxCms\Core\Strings\StringCatalog;

/*
 * Strings in the views @matrix renders, one per block type (templates/fields/{field}/{type}, or
 * templates/fields/{field}/{template}/{type} first), are the template's strings too, so editors
 * can change them. Only the types the template offers count.
 */

beforeEach(function (): void {
    courseSite();
    $this->views = testingPath('matrix-block-views');
    File::deleteDirectory($this->views);
    View::addLocation($this->views);

    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
        ['slug' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'summary']]],
        ['slug' => 'note', 'label' => 'Note', 'fields' => [['name' => 'summary']]],
    ]]);
    lynx()->templates->add('story', fields: ['title', 'blocks']);
    blockView('templates/story', "<h1>@string('story.title', 'Story')</h1>\n@matrix(\$page->blocks)");
    blockView('templates/fields/blocks/quote', "<blockquote>@string('quote.label', 'Quote', scope: 'site')</blockquote>");
});

function blockView(string $name, string $source): void
{
    File::ensureDirectoryExists(dirname(test()->views.'/'.$name));
    File::put(test()->views.'/'.$name.'.blade.php', $source);
}

/** @return list<string> */
function storyStrings(): array
{
    $keys = array_keys(app(StringCatalog::class)->forTemplate('story'));
    sort($keys);

    return $keys;
}

it('finds the strings of the block views of the template\'s matrix fields', function (): void {
    expect(storyStrings())->toBe(['quote.label', 'story.title'])
        ->and(app(StringCatalog::class)->forTemplate('story')['quote.label']->scope)->toBe('site');
});

it('reads the block view the template renders, its own before the shared one', function (): void {
    blockView('templates/fields/blocks/story/quote', "<blockquote>@string('story.quote', 'Quote')</blockquote>");

    expect(storyStrings())->toBe(['story.quote', 'story.title']);
});

it('reads only the block types the template offers', function (): void {
    blockView('templates/fields/blocks/note', "<aside>@string('note.label', 'Note')</aside>");
    lynx()->templates->update('story', ['fields' => ['title', ['name' => 'blocks', 'allowed_types' => ['note']]]]);

    expect(storyStrings())->toBe(['note.label', 'story.title']);
});

it('scans again when a block view is created', function (): void {
    expect(storyStrings())->toBe(['quote.label', 'story.title']);

    blockView('templates/fields/blocks/note', "<aside>@string('note.label', 'Note')</aside>");
    app()->forgetInstance(StringCatalog::class);

    expect(storyStrings())->toBe(['note.label', 'quote.label', 'story.title']);
});

it('scans again when the matrix gains a block type', function (): void {
    blockView('templates/fields/blocks/video', "<figure>@string('video.caption', 'Video')</figure>");
    expect(storyStrings())->toBe(['quote.label', 'story.title']);

    lynx()->fields->update('blocks', ['settings' => ['types' => [
        ['slug' => 'quote', 'label' => 'Quote', 'fields' => [['name' => 'summary']]],
        ['slug' => 'note', 'label' => 'Note', 'fields' => [['name' => 'summary']]],
        ['slug' => 'video', 'label' => 'Video', 'fields' => [['name' => 'summary']]],
    ]]]);
    app()->forgetInstance(StringCatalog::class);

    expect(storyStrings())->toBe(['quote.label', 'story.title', 'video.caption']);
});
