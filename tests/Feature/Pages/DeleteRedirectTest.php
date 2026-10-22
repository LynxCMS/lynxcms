<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\DeleteRedirect;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\OldPath;
use LynxCms\Core\Pages\Page;

beforeEach(fn (): Lynx => courseSite());

function renamedCourse(): Page
{
    $alpha = createCourses('alpha')['alpha'];
    $alpha->name = 'alpha-2';
    $alpha->setLanguageName('pt', 'alfa');
    $alpha->save();
    $alpha->setLanguageName('pt', 'alfa-2');
    $alpha->save();

    return lynx()->pages->get($alpha->id);
}

/** @return list<string> */
function oldUrls(Page $page): array
{
    return array_map(fn (OldPath $old): string => $old->url(), lynx()->pages->redirects($page->id));
}

it('deletes an old path so it no longer redirects', function (): void {
    $alpha = renamedCourse();

    lynx()->pages->deleteRedirect($alpha->id, 'courses/alpha');

    expect(oldUrls($alpha))->toBe(['/pt/cursos/alfa']);
    $this->get('/courses/alpha')->assertNotFound();
});

it('deletes an old path in another language, ignoring slashes and case', function (): void {
    $alpha = renamedCourse();

    lynx()->pages->deleteRedirect($alpha, '/Cursos/ALFA', 'pt');

    expect(oldUrls($alpha))->toBe(['/courses/alpha']);
});

it('refuses the current path and paths the page never had', function (string $path, string $language): void {
    $alpha = renamedCourse();

    expect(fn () => lynx()->pages->deleteRedirect($alpha, $path, $language))->toThrow(ValidationException::class, 'old URL');
})->with([
    'current path' => ['courses/alpha-2', 'default'],
    'unknown path' => ['courses/nope', 'default'],
    'wrong language' => ['courses/alpha', 'pt'],
]);

it('refuses on a locked page', function (): void {
    $alpha = renamedCourse();
    lynx()->pages->setStatus($alpha, add: ['locked']);

    expect(fn () => lynx()->pages->deleteRedirect($alpha, 'courses/alpha'))->toThrow(ValidationException::class, 'locked');
});

it('reports a destructive change on a dry run and keeps the path', function (): void {
    $alpha = renamedCourse();

    $result = lynx()->pages->deleteRedirect($alpha, 'courses/alpha', dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->isDestructive())->toBeTrue()
        ->and($result->changes->summary()[0])->toContain('/courses/alpha')
        ->and(oldUrls($alpha))->toContain('/courses/alpha');
});

it('shows in the page\'s activity and is offered to agents', function (): void {
    $alpha = renamedCourse();

    lynx()->pages->deleteRedirect($alpha, 'courses/alpha');

    expect(lynx()->pages->activity($alpha)[0]->action)->toBe('redirects.delete')
        ->and(lynx()->actions()->find('redirects.delete'))->toBe(DeleteRedirect::class);
});
