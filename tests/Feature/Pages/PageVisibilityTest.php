<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LynxCms\Admin\Admin;
use LynxCms\Core\Http\Middleware\WithoutPageVisibility;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Core\Support\Tables;
use LynxCms\Mcp\McpServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/*
 * ADR-0062: page gates answer for a page before it renders; visibility rules leave pages out of
 * what visitors find, but not out of the admin, commands or MCP.
 */

/** Hides pages whose name starts with "secret". */
final class HidesSecretPages implements VisibilityRule
{
    public function apply(Builder $query): void
    {
        $query->whereNotIn(Tables::PAGES.'.id', $this->ids());
    }

    public function allows(Page $page): bool
    {
        return ! in_array((int) $page->id, $this->ids(), true);
    }

    /** @return list<int> */
    private function ids(): array
    {
        return app(PageVisibility::class)->withoutRules(fn (): array => lynx()->pages->find('template=course, name^=secret')->map(fn (Page $page): int => (int) $page->id));
    }
}

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha', 'secret-one');
    $this->secret = lynx()->pages->get('/courses/secret-one/');
});

$names = fn (): array => lynx()->pages->find('template=course, sort=name')->map(fn (Page $page): string => (string) $page->name);

it('changes nothing without rules', function () use ($names): void {
    expect($names())->toBe(['alpha', 'secret-one']);
});

it('leaves the pages a rule refuses out of selectors and counts', function () use ($names): void {
    app(PageVisibility::class)->add(new HidesSecretPages);

    expect($names())->toBe(['alpha'])
        ->and(lynx()->pages->count('template=course'))->toBe(1)
        ->and(app(PageVisibility::class)->allows($this->secret))->toBeFalse()
        ->and(lynx()->pages->get('/courses/secret-one/'))->toBeInstanceOf(Page::class)
        ->and(app(PageVisibility::class)->withoutRules(fn (): array => $names()))->toBe(['alpha', 'secret-one']);
});

it('drops refused pages from page reference fields', function (): void {
    $this->alpha->related = [$this->secret->id];
    $this->alpha->save();
    app(PageVisibility::class)->add(new HidesSecretPages);

    expect(count(lynx()->pages->get('/courses/alpha/')?->related ?? []))->toBe(0);
});

it('lets commands, queue workers and the scheduler see every page', function () use ($names): void {
    app(PageVisibility::class)->add(new HidesSecretPages);
    $environment = app()['env'];
    app()['env'] = 'production';

    try {
        expect(app()->runningInConsole())->toBeTrue()->and($names())->toBe(['alpha', 'secret-one']);
    } finally {
        app()['env'] = $environment;
    }
});

it('applies the rules in a console process inside asVisitor, and only there', function () use ($names): void {
    app(PageVisibility::class)->add(new HidesSecretPages);
    $environment = app()['env'];
    app()['env'] = 'production';

    try {
        expect(app(PageVisibility::class)->asVisitor(fn (): array => $names()))->toBe(['alpha'])
            ->and($names())->toBe(['alpha', 'secret-one']);
    } finally {
        app()['env'] = $environment;
    }
});

it('lets admin routes see every page', function (): void {
    app(PageVisibility::class)->add(new HidesSecretPages);
    Admin::routes(fn () => Route::get('visible-count', fn (): string => (string) lynx()->pages->count('template=course'))->name('visible-count'));
    Route::get('front-count', fn (): string => (string) lynx()->pages->count('template=course'))->middleware('web');
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs(new GenericUser(['id' => 1]));

    $this->get('/lynx/visible-count')->assertOk()->assertSee('2');
    $this->get('/front-count')->assertOk()->assertSee('1');
});

it('gives the MCP web route the middleware', function (): void {
    config(['lynx.mcp.web.enabled' => true]);
    new McpServiceProvider(app())->boot();
    Route::getRoutes()->refreshNameLookups();

    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route): bool => $route->uri() === 'mcp/lynx' && in_array('POST', $route->methods(), true));

    expect($route?->gatherMiddleware())->toContain(WithoutPageVisibility::class);
});

it('sends a gate response instead of the page', function (): void {
    app(PageGates::class)->add(fn (Page $page, Request $request): ?Response => $page->id === $this->secret->id ? response('locked', 403) : null);

    $this->get('/courses/secret-one/')->assertForbidden()->assertSee('locked');
    $this->get('/courses/alpha/')->assertOk();
});
