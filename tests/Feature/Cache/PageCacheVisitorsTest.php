<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;

/*
 * The page cache stores only what a guest without cookies or an account sees. A visibility rule
 * that lets some guests see more (a header, an address range, a cookie) must never put its
 * version in the shared copy.
 */

/** Shows more to guests who send X-Partner: yes, and says so through its audience. */
final class PartnerRule implements VariesByVisitor, VisibilityRule
{
    public function apply(Builder $query): void {}

    public function allows(Page $page): bool
    {
        return true;
    }

    public function audience(): ?string
    {
        return request()->header('X-Partner') === 'yes' ? 'partner' : null;
    }
}

/** A rule that cannot say who sees what. */
final class OpaqueRule implements VisibilityRule
{
    public function apply(Builder $query): void {}

    public function allows(Page $page): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    config(['lynx-cache.pages.enabled' => true, 'lynx-cache.pages.path' => testingPath('lynx-cache-visitors'), 'lynx-cache.pages.debug' => true]);
    File::deleteDirectory(testingPath('lynx-cache-visitors'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('note', '/');
    $page->name = 'a';
    $page->title = 'Note a';
    $page->save();
});

afterEach(fn () => File::deleteDirectory(testingPath('lynx-cache-visitors')));

it('stores what a guest without cookies sees', function (): void {
    app(PageVisibility::class)->add(new PartnerRule);

    $this->get('/a/')->assertHeader('X-Lynx-Cache', 'MISS');
});

it('never stores the page a rule shows to some guests only', function (): void {
    app(PageVisibility::class)->add(new PartnerRule);

    $this->get('/a/', ['X-Partner' => 'yes'])->assertOk()->assertHeader('X-Lynx-Cache', 'SKIP; reason=visitor');
});

it('stores nothing while a rule cannot say who sees what', function (): void {
    app(PageVisibility::class)->add(new OpaqueRule);

    $this->get('/a/')->assertOk()->assertHeader('X-Lynx-Cache', 'SKIP; reason=visitor');
});
