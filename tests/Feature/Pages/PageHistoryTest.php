<?php

declare(strict_types=1);

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\OldPath;
use LynxCms\Core\Pages\Page;

beforeEach(fn (): Lynx => courseSite());

function renamed(Page $page, string $name, ?string $language = null): Page
{
    $language === null ? $page->name = $name : $page->setLanguageName($language, $name);
    $page->save();

    return lynx()->pages->get($page->id);
}

it('lists the old paths of a page per language, newest first', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha = renamed($alpha, 'alpha-2');
    $alpha = renamed($alpha, 'alpha-3');
    renamed($alpha, 'alfa', 'pt');
    renamed(lynx()->pages->get($alpha->id), 'alfa-2', 'pt');

    $redirects = lynx()->pages->redirects($alpha->id);

    expect(array_map(fn (OldPath $old): string => $old->url(), $redirects))
        ->toBe(['/pt/cursos/alfa', '/courses/alpha-2', '/courses/alpha'])
        ->and($redirects[0]->language->code)->toBe('pt')
        ->and($redirects[0]->isRedirecting)->toBeTrue();
});

it('has no old paths for a page that was never renamed', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');

    expect(lynx()->pages->redirects($alpha))->toBe([]);
});

it('leaves out an old path the page uses again', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    renamed(renamed($alpha, 'alpha-2'), 'alpha');

    expect(array_map(fn (OldPath $old): string => $old->url(), lynx()->pages->redirects($alpha->id)))->toBe(['/courses/alpha-2']);
});

it('marks an old path another page now uses as no longer redirecting', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    renamed($alpha, 'alpha-2');
    createCourses('alpha');

    [$old] = lynx()->pages->redirects($alpha->id);

    expect([$old->url(), $old->isRedirecting])->toBe(['/courses/alpha', false]);
});

it('finds the pages that reference a page', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = createCourses('alpha', 'beta', 'gamma', 'delta');
    $beta->related = [$alpha->id];
    $beta->save();
    $gamma->related = [$beta->id, $alpha->id];
    $gamma->save();
    lynx()->pages->setStatus($gamma->id, add: ['unpublished']);

    expect(lynx()->pages->backlinks($alpha->id)->map(fn (Page $page): string => $page->name()))->toBe(['beta', 'gamma'])
        ->and(lynx()->pages->backlinks($gamma->id)->count())->toBe(0);
});

it('narrows the backlinks with a selector', function (): void {
    lynx()->templates->add('note', fields: ['related']);
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $beta->related = [$alpha->id];
    $beta->save();
    $note = lynx()->pages->new('note', '/');
    $note->name = 'note';
    $note->related = [$alpha->id];
    $note->save();

    expect(lynx()->pages->backlinks($alpha, selector: 'template!=note')->map(fn (Page $page): string => $page->name()))->toBe(['beta']);
});

it('caps the backlinks it returns', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    foreach (['beta', 'gamma'] as $name) {
        $pages[$name]->related = [$pages['alpha']->id];
        $pages[$name]->save();
    }

    expect(lynx()->pages->backlinks($pages['alpha'], limit: 1)->count())->toBe(1);
});

it('lists the activity of a page, newest first, with who did it', function (): void {
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    lynx()->actingAs(Actor::user(7), fn (): Page => lynx()->pages->setStatus($alpha->id, add: ['hidden']));
    lynx()->pages->setStatus($beta->id, add: ['hidden']);

    $activity = lynx()->pages->activity($alpha->id);

    expect(array_map(fn (ActivityEvent $event): string => $event->action, $activity))->toBe(['pages.status', 'pages.save'])
        ->and([$activity[0]->actor_kind, $activity[0]->actor_id])->toBe([Actor::USER, '7'])
        ->and($activity[0]->changes[0]['summary'])->toBeString();
});

it('limits the activity it returns', function (): void {
    ['alpha' => $alpha] = createCourses('alpha');
    renamed($alpha, 'alpha-2');

    expect(lynx()->pages->activity($alpha->id, limit: 1))->toHaveCount(1);
});
