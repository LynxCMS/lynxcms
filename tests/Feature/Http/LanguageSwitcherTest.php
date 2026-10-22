<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
});

function switchable(): Page
{
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->setLanguageName('pt', 'alfa', active: true);
    $alpha->save();

    return lynx()->pages->get('/courses/alpha');
}

it('links to the page in each language, keeping the query string', function (): void {
    $page = switchable();
    $this->get('/courses/alpha?level=advanced&tags[]=agile&preview=draft');

    $links = lynx()->languages->switcher($page);

    expect(array_map(fn (array $link): array => [$link['language']->code, $link['url'], $link['isCurrent']], $links))->toBe([
        ['default', '/courses/alpha?level=advanced&tags%5B0%5D=agile&preview=draft', true],
        ['pt', '/pt/cursos/alfa?level=advanced&tags%5B0%5D=agile&preview=draft', false],
    ]);
});

it('leaves out languages the page is not published in', function (): void {
    expect(array_map(fn (array $link): string => $link['language']->code, lynx()->languages->switcher(lynx()->pages->get('/courses/alpha'))))->toBe(['default']);
});

it('keeps the query string in the demo site\'s switcher, and out of the hreflang tags', function (): void {
    switchable();

    $this->get('/courses/alpha?level=advanced')
        ->assertSee('href="/pt/cursos/alfa?level=advanced"', false)
        ->assertSee('hreflang="pt-PT" href="http://localhost/pt/cursos/alfa"', false)
        ->assertDontSee('hreflang="pt-PT" href="http://localhost/pt/cursos/alfa?', false);
});
