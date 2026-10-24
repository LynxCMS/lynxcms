<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\Access\PasswordVisibility;
use LynxCms\PagePassword\Access\ProtectedRoots;
use LynxCms\PagePassword\Access\Unlocks;

/**
 * Checks a password typed in the form. Right: the page that holds it is unlocked in this browser
 * for remember_days. Wrong, or too many tries (per address each minute, or wrong ones for the page
 * from every address each hour): back to the form with the reason. Always redirects
 * to the page itself, never to an address from the request.
 *
 * @internal
 */
final readonly class UnlockController
{
    /** Tries per minute, per address and protected page. */
    private const int MAX_TRIES = 5;

    private const int SECONDS_PER_HOUR = 3600;

    private const int MINUTES_PER_DAY = 1440;

    public function __construct(
        private PageLoader $loader,
        private PasswordVisibility $visibility,
        private ProtectedRoots $roots,
        private Unlocks $unlocks,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $input = Value::map($request->validate(['page' => ['required', 'integer'], 'password' => ['required', 'string', 'max:200'], 'language' => ['nullable', 'string']]));
        $page = $this->loader->find(Value::int($input['page']));
        $root = $page instanceof Page ? $this->visibility->nearestRoot((int) $page->id) : null;
        abort_if(! $page instanceof Page || $root === null, 404);

        $language = lynx()->languages->find(Value::string($input['language'] ?? '')) ?? lynx()->languages->default();
        $back = redirect($page->localUrl($language));
        $key = 'lynx-page-password:'.$request->ip().':'.$root;

        $pageKey = 'lynx-page-password:page:'.$root;
        $maxFailures = Value::int(config('lynx-page-password.max_failures_per_hour', 100), 100);

        if (RateLimiter::tooManyAttempts($pageKey, $maxFailures)) {
            return $back->withErrors(['password' => __('lynx-page-password::form.locked')]);
        }

        if (RateLimiter::tooManyAttempts($key, self::MAX_TRIES)) {
            return $back->withErrors(['password' => __('lynx-page-password::form.too_many')]);
        }

        RateLimiter::hit($key);

        if (! ($this->roots->passwords()[$root] ?? null)?->matches(trim(Value::string($input['password'])))) {
            $this->countFailure($pageKey, $maxFailures, $root);

            return $back->withErrors(['password' => __('lynx-page-password::form.wrong')]);
        }

        RateLimiter::clear($key);
        $days = Value::int(config('lynx-page-password.remember_days', 30));

        return $back->withCookie(cookie(Unlocks::COOKIE, $this->unlocks->cookieValue([$root]), $days * self::MINUTES_PER_DAY));
    }

    /**
     * Wrong passwords for the page from every address together: past the limit, the page refuses
     * every try for the rest of the hour, and the log says so once.
     */
    private function countFailure(string $pageKey, int $maxFailures, int $root): void
    {
        if (RateLimiter::hit($pageKey, self::SECONDS_PER_HOUR) === $maxFailures) {
            Log::warning("Page [{$root}] had {$maxFailures} wrong passwords within an hour; it refuses every try until the hour is over. Someone may be guessing its password.");
        }
    }
}
