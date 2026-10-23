<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\TemplateUsage;

/*
 * A template can be deleted only once no page uses it, trashed pages included. Refusing names the
 * pages and the way out, so people and agents can switch them to another template first.
 */

beforeEach(function (): void {
    installLynx();
    lynx()->templates->add('article', fields: ['title']);
    lynx()->templates->add('story', fields: ['title']);
});

function articlePage(string $name, string $title): Page
{
    $page = lynx()->pages->new('article', '/');
    $page->name = $name;
    $page->title = $title;
    $page->save();

    return $page;
}

/** The message templates.delete refuses with. */
function deleteRefusal(string $template): string
{
    try {
        lynx()->templates->delete($template);
    } catch (ValidationException $exception) {
        return implode(' ', $exception->errors()['template'] ?? []);
    }

    return '';
}

it('names the pages that use a template it refuses to delete, and the way out', function (): void {
    articlePage('first', 'First');
    articlePage('second', 'Second');

    expect(deleteRefusal('article'))
        ->toContain('Template [article] is used by 2 pages')
        ->toContain('First (/first)')
        ->toContain('Second (/second)')
        ->toContain('pages.switch_template');
});

it('counts pages in the trash, and says so', function (): void {
    $page = articlePage('gone', 'Gone');
    lynx()->run(TrashPage::class, ['page' => $page->id]);

    expect(deleteRefusal('article'))
        ->toContain('is used by 1 page')
        ->toContain('Gone (in the trash)');
});

it('names the first pages and counts the rest', function (): void {
    foreach (range(1, TemplateUsage::NAMED + 2) as $n) {
        articlePage("page-{$n}", "Page {$n}");
    }

    expect(deleteRefusal('article'))->toContain('and 2 more');
});

it('deletes the template once its pages use another one', function (): void {
    $page = articlePage('first', 'First');
    lynx()->pages->switchTemplate($page, 'story');

    lynx()->templates->delete('article');

    expect(lynx()->templates->find('article'))->toBeNull();
});

it('lists the pages using a template, trashed ones marked', function (): void {
    articlePage('first', 'First');
    $gone = articlePage('gone', 'Gone');
    lynx()->run(TrashPage::class, ['page' => $gone->id]);
    $usage = app(TemplateUsage::class);
    $template = lynx()->templates->get('article');

    expect($usage->count($template))->toBe(2)
        ->and(array_map(fn (array $page): array => [$page['title'], $page['trashed']], $usage->pages($template, 10)))
        ->toBe([['First', false], ['Gone', true]]);
});
