<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Core\Support\Tables;

/*
 * Sitemaps are cached for every guest, so only a plain guest's build is kept: a visitor a rule
 * shows more to gets a sitemap built for them alone, and search engines never see their pages.
 */

beforeEach(function (): void {
    courseSite();
    ['hidden' => $hidden] = createCourses('open', 'hidden');
    app(PageVisibility::class)->add(new readonly class((int) $hidden->id) implements VariesByVisitor, VisibilityRule
    {
        public function __construct(private int $hiddenId) {}

        public function apply(Builder $query): void
        {
            if ($this->audience() === null) {
                $query->where(Tables::PAGES.'.id', '!=', $this->hiddenId);
            }
        }

        public function allows(Page $page): bool
        {
            return $page->id !== $this->hiddenId || $this->audience() !== null;
        }

        public function audience(): ?string
        {
            return request()->header('X-Partner') === 'yes' ? 'partner' : null;
        }
    });
});

it('builds a sitemap for a visitor a rule treats apart without sharing it', function (): void {
    $partner = $this->get('/sitemap.xml', ['X-Partner' => 'yes'])->assertOk();

    expect($partner->getContent())->toContain('/courses/hidden')
        ->and($partner->headers->get('Cache-Control'))->toContain('no-store');

    $guest = $this->get('/sitemap.xml')->assertOk();

    expect($guest->getContent())->toContain('/courses/open')->not->toContain('/courses/hidden');
});
