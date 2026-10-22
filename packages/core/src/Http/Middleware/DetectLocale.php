<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;

/**
 * Required. Resolves the request language (ADR-0007): URL prefix first; without a prefix the
 * session, then the logged-in user's language, then the default. Sets LanguageContext and
 * Laravel's locale, and stores the path without prefix in the "lynx.path" request attribute.
 */
final readonly class DetectLocale
{
    public function __construct(
        private LanguageRegistry $languages,
        private LanguageContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->languages->hasDefault()) {
            return $next($request);
        }

        $segments = explode('/', trim($request->path(), '/'));
        $first = $segments[0] ?? '';
        $byPrefix = $first === '' ? null : $this->languages->byPrefix($first);

        if ($byPrefix !== null && $byPrefix->is_active) {
            $language = $byPrefix;
            array_shift($segments);
            $request->attributes->set('lynx.prefixed', true);
        } else {
            $language = $this->unprefixedLanguage($request);
            $request->attributes->set('lynx.prefixed', false);
        }

        $request->attributes->set('lynx.path', implode('/', $segments));
        $request->attributes->set('lynx.language', $language->code);
        $this->context->set($language);

        return $next($request);
    }

    /** For URLs without a prefix: the empty-prefix language for pages, else session/user/default. */
    private function unprefixedLanguage(Request $request): Language
    {
        $remembered = $request->hasSession() ? $request->session()->get(SetCurrentLanguage::SESSION_KEY) : null;
        $fromSession = $remembered === null ? null : $this->languages->find(Value::string($remembered));
        // data_get works for Eloquent users and for any Authenticatable with magic getters.
        $userLanguageId = data_get($request->user(), 'lynx_language_id');
        $fromUser = $userLanguageId === null ? null : $this->languages->find(Value::int($userLanguageId));

        if ($request->attributes->get('lynx.page_route') === true) {
            // Page URLs decide the language: no prefix means the language without a prefix.
            return $this->languages->byPrefix('') ?? $this->languages->default();
        }

        foreach ([$fromSession, $fromUser] as $candidate) {
            if ($candidate !== null && $candidate->is_active) {
                return $candidate;
            }
        }

        return $this->languages->default();
    }
}
