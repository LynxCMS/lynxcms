<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Fragments\Fragments;
use LynxCms\Core\Actions\Runner\ActionResult;

beforeEach(function (): void {
    courseSite();
    createCourses('risk', 'agile');
    $this->renders = 0;
});

/** Remembers a fragment whose closure counts how often it ran. */
function fragment(string $name = 'menu', array $options = []): string
{
    return lynx()->cache()->remember($name, function () use ($name): string {
        test()->renders++;

        return "<nav>{$name} ".lynx()->languages->current()->code.'</nav>';
    }, ...$options);
}

it('renders a fragment once and serves it again', function (): void {
    expect(fragment())->toBe('<nav>menu default</nav>')
        ->and(fragment())->toBe('<nav>menu default</nav>')
        ->and($this->renders)->toBe(1)
        ->and(FragmentEntry::query()->where('name', 'menu')->value('size'))->toBe(strlen('<nav>menu default</nav>'));
});

it('keeps one copy per language and per vary value', function (): void {
    fragment();
    lynx()->language('pt', fn (): string => fragment());
    fragment('menu', ['vary' => ['compact']]);

    expect($this->renders)->toBe(3)->and(FragmentEntry::query()->count())->toBe(3);
});

it('clears a fragment without dependencies on any content change', function (): void {
    fragment();
    lynx()->pages->get('/courses/risk/')?->save();

    fragment();

    expect($this->renders)->toBe(2);
});

it('clears a fragment only when what it depends on changes', function (): void {
    $risk = lynx()->pages->get('/courses/risk/');
    fragment('risk', ['dependsOn' => ['page:'.$risk?->id]]);
    fragment('courses', ['dependsOn' => ['template:courses']]);

    lynx()->pages->get('/courses/agile/')?->save();
    fragment('risk', ['dependsOn' => ['page:'.$risk?->id]]);
    fragment('courses', ['dependsOn' => ['template:courses']]);
    expect($this->renders)->toBe(2);

    $risk?->save();
    lynx()->pages->get('/courses/')?->save();
    fragment('risk', ['dependsOn' => ['page:'.$risk?->id]]);
    fragment('courses', ['dependsOn' => ['template:courses']]);
    expect($this->renders)->toBe(4);
});

it('clears every fragment when a language or the schema changes', function (): void {
    fragment('risk', ['dependsOn' => ['page:1']]);

    lynx()->languages->add('es', 'Español');

    expect(FragmentEntry::query()->count())->toBe(0);
});

it('expires a fragment after its lifetime', function (): void {
    fragment('menu', ['ttl' => 60]);
    $this->travel(61)->seconds();

    fragment('menu', ['ttl' => 60]);

    expect($this->renders)->toBe(2);
});

it('renders for signed in users without storing', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    fragment();
    fragment();

    expect($this->renders)->toBe(2)->and(FragmentEntry::query()->count())->toBe(0);
});

it('does not store a fragment holding the visitor\'s CSRF token', function (): void {
    $this->startSession();

    $html = lynx()->cache()->remember('form', fn (): string => '<input name="_token" value="'.csrf_token().'">');

    expect($html)->toContain(csrf_token())->and(FragmentEntry::query()->count())->toBe(0);
});

it('caches what @lynxCache wraps', function (): void {
    $view = "@lynxCache('footer', ['vary' => ['a']])<footer>{{ \$n }}</footer>@endLynxCache";

    expect(Blade::render($view, ['n' => 1]))->toBe('<footer>1</footer>')
        ->and(Blade::render($view, ['n' => 2]))->toBe('<footer>1</footer>');
});

it('nests @lynxCache blocks', function (): void {
    $view = "@lynxCache('outer')<div>@lynxCache('inner')<span>{{ \$n }}</span>@endLynxCache {{ \$n }}</div>@endLynxCache";

    expect(Blade::render($view, ['n' => 1]))->toBe('<div><span>1</span> 1</div>')
        ->and(Blade::render($view, ['n' => 2]))->toBe('<div><span>1</span> 1</div>')
        ->and(FragmentEntry::query()->pluck('name')->sort()->values()->all())->toBe(['inner', 'outer']);
});

it('clears every fragment, or one by name, through page_cache.clear', function (): void {
    fragment('menu');
    fragment('footer');

    lynx()->run(ClearPageCache::class, ['fragment' => 'menu']);
    expect(FragmentEntry::query()->pluck('name')->all())->toBe(['footer']);

    $dry = lynx()->run(ClearPageCache::class, ['fragments' => true], dryRun: true);
    expect($dry->changes->all()[0]->summary)->toContain('1 fragment')->and(FragmentEntry::query()->count())->toBe(1);

    lynx()->run(ClearPageCache::class, ['fragments' => true]);
    expect(FragmentEntry::query()->count())->toBe(0)->and(app(Fragments::class))->toBeInstanceOf(Fragments::class);
});

it('refuses to clear fragments and pages in one go', function (): void {
    expect(fn (): ActionResult => lynx()->run(ClearPageCache::class, ['fragments' => true, 'pages' => [1]]))->toThrow(ValidationException::class);
});
