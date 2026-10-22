<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use LynxCms\Core\Models\Language;

/**
 * A path a page had before a rename or move. It redirects to the page's current URL unless
 * another page has since taken it.
 *
 * @api
 */
final readonly class OldPath
{
    public function __construct(
        public Language $language,
        public string $path,
        public bool $isRedirecting,
    ) {}

    /** Relative URL with the language prefix, e.g. "/pt/cursos/alfa/". */
    public function url(): string
    {
        return PageUrl::of($this->language->url_prefix, $this->path);
    }
}
