# Plan: password-protected pages

## Goal

A site owner installs Lynx on its own and wants some pages, or every page of a template, to ask guests for a password before showing them on the front end. The password is set on the page, in a field, and pages below a protected page share it. No visitor accounts.

## Decisions taken

| Question | Decision |
| --- | --- |
| Who can open a protected page | Anyone who types its password. No accounts, no roles. |
| Where the password lives | A `page_password` field on the templates that ask for it. A page with a password protects itself and the pages below it; a page below with its own password uses that one instead (the nearest password wins). |
| What guests see before unlocking | Nothing. Locked pages are left out of selectors, search, menus, page reference fields and the sitemap, and are `noindex`. Opening a locked page's address shows the password form. |
| How long an unlock lasts | 30 days by default (`lynx-page-password.remember_days`), or until the password changes. |
| How the password is stored | Encrypted with the app key (Laravel `Crypt`), so editors can read it again to send it to guests, and compared in constant time. |
| Signed-in users | They skip the form for the pages their roles let them view in the admin (the admin's view scope, ADR-0033), as with draft previews. Everyone else needs the password. |
| Packaging | A module, `pagePassword`: the Composer package `lynxcms/page-password` (Composer names are lowercase), namespace `LynxCms\PagePassword`, actions `page_password.*`. The core gets two small extension points, the module does the rest. |
| Files and images | Their URLs stay public, as media URLs are today. |

## Part 1: core extension points (ADR-0062)

The core today renders any published page (`PageController`) and lets every selector see every published page. Two `@api` registries change that without the core knowing about passwords:

1. **`PageGates`**: closures `fn (Page $page, Request $request): ?Response` that `PageController` asks before rendering. The first response wins (the password form). Previews and redirects keep working as now.
2. **`PageVisibility`**: what a front-end visitor may see.
   - `filter(Closure(Builder): void)` narrows front-end selector queries, the way `StatusFilter` leaves out unpublished branches.
   - `allows(Page): bool` answers for one page, for code that loads pages directly: page reference fields (`StoresPageReferences::present()`), menus, `children()` counts.
   - Filters apply during HTTP requests outside the admin, including front-end Livewire updates. They never apply in the console, to MCP and agent tools, or inside the admin, so exports, backups, the sitemap builder and the search index see every page.
   - `lynx()->pages->get()` by id or path is not filtered: templates use it for known pages such as settings. The docs say so.

## Part 2: the `pagePassword` module (`lynxcms/page-password`)

### Setup

```bash
composer require lynxcms/page-password
php artisan lynx:page-password:install   # or the page_password.install action; no tables needed
```

`page_password.enable_template` adds the `page_password` field to a template, in the Content tab's sidebar (no section, so nothing is left behind when it is removed); `page_password.disable_template` removes it and keeps the values, like the SEO actions (ADR-0055). The admin gets **Settings > Page passwords**: templates on or off, and the list of protected pages.

### The password field

- A `password` field type in the module: stored encrypted, never compared in selectors (`Comparison::None`), never written to the search index.
- The admin input is masked, with show and generate buttons. Empty means not protected.
- The database only ever holds the encrypted value, so read tools (`get_page`, MCP), page history and content packages see ciphertext, never the password; no extra masking is needed. Importing on a site with another app key keeps the page protected (it fails closed), but the password must be set again.

### Which pages are locked

- `ProtectedRoots` lists the pages with a password: few, cached per request and forgotten after `PageSaved`, `PageDeleted`, `PageMoved` and `PageTemplateSwitched`.
- A page is locked for a visitor when the nearest protected page at or above it is not unlocked in their browser. Computed with the closure table in one query, and skipped entirely when no page has a password, as `UnpublishedBranches` does.
- The visibility filter leaves locked pages out of front-end selectors. `allows()` answers the same for single pages.

### Unlocking

- A locked page answers `403` with the password form (an overridable view, `lynx-page-password::form`, translatable texts), `noindex` and `Cache-Control: no-store, private`.
- The form posts to `POST /lynx-page-password/unlock` with the page id. CSRF protected, and limited to 5 tries a minute per address and page.
- On success the module sets an encrypted cookie holding, per unlocked root page, a fingerprint of its stored password. It then redirects to the page's own address, never to a URL from the request.
- A wrong password shows the form again with an error. A changed password changes the fingerprint, so old unlocks stop working.
- Unlocked protected pages are still sent with `no-store, private` and `noindex`, so no cache or search engine keeps them.

### Everywhere else

- **Sitemap:** a `SitemapFilters` rule leaves protected pages out, locked or not.
- **SEO:** protected pages are sent with `X-Robots-Tag: noindex` (step 3), which search engines read like the robots meta tag, and crawlers never see their content; so the SEO resolver needs no hook.
- **Menus:** items pointing at a locked page are left out for that visitor.
- **Page tree:** a key beside protected pages (a new admin `TreeMarkers` registry; the lock icon already means a locked page); the page editor links "Protected by Members" on pages below a protected one.

## Tests (TDD, SQLite and PostgreSQL)

- Gates and visibility in core:
  - a gate's response replaces the page;
  - filters narrow front-end finds and not admin, console or MCP finds;
  - `allows()` drops pages from page reference fields.
- Inheritance:
  - a password on `/members/` locks its descendants;
  - a nearer password overrides it;
  - unlocking the parent opens children that inherit, not those with their own password.
- Unlocking:
  - the right password sets the cookie and redirects to the page;
  - a wrong one shows the error;
  - the sixth try in a minute is refused;
  - changing the password locks the page again;
  - the cookie expires after the configured days.
- Hidden everywhere: locked pages are missing from selectors, search, menus, the sitemap and reference fields, and present again once unlocked (sitemap excepted).
- The form response is `403`, `noindex` and `no-store`; unlocked pages are `noindex` and `no-store`.
- Signed-in users skip the form only for pages in their admin view scope.
- Read tools and the activity log never show the password; content packages round-trip it.
- Actions: happy path, validation failure, rollback and dry run for install, enable and disable.
- Browser check with agent-browser: lock, unlock, the remembered unlock, the admin field.

## Order of work

1. ADR-0062 and the two core registries, with their tests. One PR.
2. The module: field type, actions, roots and the visibility filter. One PR.
3. The unlock flow: form, route, setting the cookie, rate limit, headers. One PR. (Until it landed, a locked page's own address still rendered: step 2 only hid locked pages from listings.)
4. Integrations (sitemap, SEO, menus, tree icon, admin screen) and docs, then a browser check on the workbench. One PR.

## Out of scope

- Visitor accounts, member areas and roles for guests. A later module could reuse `PageGates` and `PageVisibility`.
- Protecting files and images: their URLs stay public, as media URLs are today (decided). The docs say so.
- Teasers (listing locked pages with their title): this plan hides them everywhere, as decided.
