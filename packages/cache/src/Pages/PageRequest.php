<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use LynxCms\Core\Pages\Page;

/**
 * A page request the cache is following: where it would be stored, the page and its language code,
 * the template rule, and the clear revision noted before rendering.
 *
 * @internal
 */
final readonly class PageRequest
{
    /** Request attribute holding the PageRequest while a page that may be stored renders. */
    public const string ATTRIBUTE = 'lynx-cache.pending';

    /** Request attribute holding why a rendered page will not be stored, for the warmer's report. */
    public const string REASON = 'lynx-cache.reason';

    public function __construct(
        public CacheAddress $address,
        public string $revision,
        public ?Page $page = null,
        public ?string $language = null,
        public ?TemplateRule $rule = null,
    ) {}

    public function withPage(Page $page, string $language, ?TemplateRule $rule): self
    {
        return new self($this->address, $this->revision, $page, $language, $rule);
    }
}
