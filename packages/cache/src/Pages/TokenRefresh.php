<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Lets pages with forms or Livewire components be stored (ADR-0064): the visitor's CSRF token is
 * replaced by a placeholder, and a small script asks for a fresh token after the page loads and
 * puts it where the placeholder was. Livewire reads the token when it sends a request, and its
 * checksums do not depend on the session, so a stored component works for any visitor.
 *
 * @internal
 */
final class TokenRefresh
{
    public const string REFRESH = 'refresh';

    public const string PLACEHOLDER = 'lynx-cache-csrf-placeholder';

    /** Islands (IslandComponent) are filled by the same script. */
    private const string ISLAND_TAG = '<lynx-island ';

    /** The HTML to store: placeholders for the token, and the script before </body> once when the page has a token or islands. */
    public function prepare(string $html, string $token): string
    {
        $hasToken = $token !== '' && str_contains($html, $token);

        if (! $hasToken && ! str_contains($html, self::ISLAND_TAG)) {
            return $html;
        }

        $html = $hasToken ? str_replace($token, self::PLACEHOLDER, $html) : $html;

        if (str_contains($html, route('lynx-cache.refresh-token.js', absolute: false))) {
            return $html;
        }

        $position = strripos($html, '</body>');

        return $position === false ? $html.$this->scriptTag() : substr_replace($html, $this->scriptTag(), $position, 0);
    }

    /** The script tag, also printed by @lynxCsrf so code can always call lynxCsrfToken(). */
    public function scriptTag(): string
    {
        return '<script src="'.e(route('lynx-cache.refresh-token.js')).'" data-endpoint="'.e(route('lynx-cache.dynamic')).'" defer></script>';
    }

    /** What @lynxCsrf prints: the token where scripts look for it, and the refresh script. */
    public function directive(string $token): string
    {
        return '<meta name="csrf-token" content="'.e($token).'">'.$this->scriptTag();
    }

    public function script(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/js/refresh-token.js');
    }
}
