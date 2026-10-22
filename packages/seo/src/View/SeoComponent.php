<?php

declare(strict_types=1);

namespace LynxCms\Seo\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use LynxCms\Core\Pages\Page;

/**
 * <x-lynx-seo :page="$page" />: the title, meta, canonical, social and hreflang tags. It replaces
 * <title> and @hreflang in a layout's <head>. A list filtered by query string can override the robots
 * rule and canonical link: <x-lynx-seo :page="$page" robots="noindex, follow" canonical="/courses/" />.
 *
 * @api
 */
final class SeoComponent extends Component
{
    public function __construct(
        public Page $page,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
    ) {}

    public function render(): View
    {
        $overrides = array_filter(['title' => $this->title, 'description' => $this->description, 'canonical' => $this->canonical, 'robots' => $this->robots], fn (?string $value): bool => $value !== null);

        return app(SeoHead::class)->view($this->page, $overrides);
    }
}
