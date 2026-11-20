# Security review, November 2026

Reviewed on 2026-11-19, on `main` at `19b73763` (after 0.1.50, with temporary passwords, required two-factor authentication and the removal of open mode merged). Fixes are in the pull request that adds this report (ADR-0129).

## Scope

Covered: sign-in, two-factor authentication, temporary passwords and invitations; permissions in admin screens and Livewire components; the MCP server and its tokens; private media and signed links; files sent with forms; page passwords; the page cache; HTML cleaning and formatters; the selector compiler's SQL; branding uploads; `composer audit`.

Not covered: every action's `permission()` one by one (how they are enforced was checked, not each declaration), security headers on public pages, the npm dependencies of the admin and the docs, and testing against a running server.

## Findings

| Id | Severity | Finding | State |
|---|---|---|---|
| M1 | Medium | A password change kept the user's other sessions and "Remember me" cookies signed in | Fixed |
| M2 | Medium | Admin components skipped the two-factor and temporary password checks on updates | Fixed |
| L1 | Low | Two-factor setup is open to whoever signs in first | Accepted; notices added (ADR-0135) |
| L2 | Low | Sign-in attempts were limited per email and address only | Fixed |
| L3 | Low | An expired temporary password still signed in through an app's own sign-in | Fixed |
| L4 | Low | Files sent with forms downloaded without `nosniff` | Fixed |

### M1. Sessions survived a password change

The admin did not use Laravel's `AuthenticateSession`, and Lynx's own password changes (`TemporaryPasswords::give()`, `NewPasswordController`, `ChangesPassword`) changed only the hash. Fortify's reset by email did change the remember token, but no path ended other sessions. Someone holding a stolen session or remember cookie stayed signed in after the owner reset the password, or after a superuser gave a temporary password, which is what a superuser does when an account may have been broken into.

**Fix:** every admin route carries `AuthenticateSession`, which compares the password hash kept in the session and in the remember cookie with the current one. Any password change, wherever it happens, ends every other session. Nothing of our own was needed beyond adding it.

### M2. Livewire updates skipped Lynx's checks

Livewire runs only its persistent middleware on component updates. `RequireOwnPassword` and `RequireTwoFactor` were not in that list. Account security is exempt from the two-factor check and its layout holds the admin search and the link dialog, so a user without two-factor authentication could use both. An admin tab left open also kept working after a temporary password was given.

**Fix:** `AuthenticateSession`, `RequireOwnPassword` and `RequireTwoFactor` are persistent middleware.

### L1. First sign-in sets up two-factor authentication

Requiring two-factor authentication on first use means whoever signs in first with the password sets it up. This is how such requirements work. Recommended later: email the user when two-factor authentication is turned on or reset, and show superusers who has not set it up.

### L2. Sign-in limits

The `lynx-login` limiter keyed attempts on email and address, so guessing one account's password from many addresses was never slowed down. **Fix:** 30 attempts an hour per email from any address, beside 5 a minute per email and address. The trade-off, someone keeping an account's owner out for up to an hour, is documented.

### L3. Expired temporary passwords and an app's own sign-in

Expiry was checked on Laravel's `Validated` event, which an app's `Fortify::authenticateUsing` does not fire. **Fix:** `RequireOwnPassword` signs out a user whose temporary password expired, on any admin request.

### L4. `nosniff` on form files

Files sent with forms were already downloads, not shown in the browser. **Fix:** they carry `X-Content-Type-Options: nosniff` like private media.

## Documented, not changed

* Page passwords are stored encrypted, not hashed, so editors can read them. This is intended: a page password is shared, not a user's secret.
* MCP tokens are a second credential: they do not pass through the admin's two-factor authentication. Issue them with the `lynx:mcp` ability only, and revoke them with the user.
* Resetting two-factor authentication ends no session. When a phone may be stolen, give a temporary password as well.

## Checked and found sound

* **HTML:** rich text is cleaned on every save with Symfony's HtmlSanitizer (an element allowlist, `http`, `https`, `mailto` and `tel` links only, no styles but text alignment). Markdown strips HTML and unsafe links. Plain text is escaped before formatters, and formatter output is cleaned again. The URL field takes `https://` addresses or paths only.
* **Files:** private media checks the page's permission, gates and visibility, and sends `nosniff` and a sandboxing CSP. Signed links are checked by Laravel's `signed` middleware. File fields can never allow types that run code (php, html, svg, js and others). SVG logos are recognised by their bytes and served with a sandboxing CSP. Form uploads go to a private disk under random names.
* **Reads:** MCP's read tools, the admin search, the link dialog and the page editor check `pages.view` and keep to the templates and branches a role covers. Ids sent by the client are checked again on the server.
* **Page cache:** only 200 HTML pages for guests are stored, never with personal cookies, a CSRF token or `no-store`.
* **Page passwords:** compared with `hash_equals`, and limited per address and per page.
* **SQL:** values always go through bindings; JSON keys interpolated into SQL are checked first (`JsonSql::assertKey()`), and `Sql::trusted()` is the only door to raw SQL.
* **Dependencies:** `composer audit` reports no advisories.
