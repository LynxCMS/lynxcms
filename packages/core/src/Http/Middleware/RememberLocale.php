<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Languages\LanguageContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional. Stores the language of every page view (GET only) in the session, so later requests
 * without a prefix (the admin, form posts, Livewire updates) stay in that language.
 */
final readonly class RememberLocale
{
    public function __construct(private LanguageContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $request->hasSession() && $response->isSuccessful()) {
            $request->session()->put(SetCurrentLanguage::SESSION_KEY, $this->context->current()->code);
        }

        return $response;
    }
}
