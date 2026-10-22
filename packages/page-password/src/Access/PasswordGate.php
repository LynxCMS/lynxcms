<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Access;

use Illuminate\Http\Request;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page gate of the module (ADR-0062): a locked page answers with the password form. A
 * protected page the visitor may open is marked, so its response is never cached or indexed.
 *
 * @internal
 */
final readonly class PasswordGate
{
    /** Request attribute set while a protected page is shown. */
    public const string PROTECTED = 'lynx.page_password.protected';

    public function __construct(private PasswordVisibility $visibility) {}

    public function __invoke(Page $page, Request $request): ?Response
    {
        if ($this->visibility->nearestRoot((int) $page->id) === null) {
            return null;
        }

        if ($this->visibility->allows($page)) {
            $request->attributes->set(self::PROTECTED, true);

            return null;
        }

        return self::private(response()->view('lynx-page-password::form', [
            'page' => $page,
            'language' => (string) app(LanguageContext::class)->current()->code,
        ], Response::HTTP_FORBIDDEN));
    }

    /** Never cached by browsers or proxies, never indexed. */
    public static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
