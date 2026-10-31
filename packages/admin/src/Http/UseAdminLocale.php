<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Admin\Locale\AdminLocale;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets Laravel's locale for an admin request (ADR-0097). Only the locale: the language pages are
 * read and saved in stays with LanguageContext.
 *
 * @internal
 */
final class UseAdminLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->getAuthIdentifier();
        app()->setLocale(AdminLocale::forUser(is_int($id) ? $id : ($id === null ? null : Value::string($id))));

        return $next($request);
    }
}
