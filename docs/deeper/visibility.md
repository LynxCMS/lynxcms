---
title: Page Access
description: Keep some pages from some visitors with visibility rules and gates, such as members-only pages.
---

# Page Access

By default, every published page is visible to anyone. When some pages are for some visitors only, such as members-only articles or a client's private area, Lynx gives you two tools, which modules such as [page passwords](/modules/page-passwords) use too:

- A **visibility rule** decides which pages a visitor may *find*. While it is active, every selector, page list, menu and page reference field leaves out the pages it refuses.
- A **gate** decides what happens when a visitor *opens* a page by its URL: it can send a response, such as a redirect to the sign-in screen, instead of the page.

A rule alone keeps pages out of lists, but someone who knows a URL could still open it; a gate alone protects URLs, but the pages would still show up in lists and menus. Restricted pages need both.

## Example: members-only pages

Say posts have a `members_only` toggle, and only signed in users may see the posts that have it on. First, the rule:

<<< @/snippets/advanced/MembersOnly.php#rule{php}

A rule has two methods, which must agree:

- `apply()` narrows the query of every selector, so lists and counts are right and fast. It receives Laravel's query builder over `lynx_pages`; a field's values are in its table, `lynx_f_{name}`, keyed by `page_id`.
- `allows()` judges one page, such as the pages a page reference field holds.

Then register the rule, with a gate for the URLs:

<<< @/snippets/advanced/members-only.php#register{php}

A gate receives the page and the request. Returning a response sends it instead of the page; returning `null` lets the page through, to the next gate or to the view. Pages that only a gate protects are still listed in the sitemap, with their URL and date; leave them out with a [sitemap filter](/modules/sitemap) when their addresses are private too.

Now guests don't see members-only posts on the blog page, in "latest posts" or in menus, and opening one's URL sends them to sign in. Signed in users see everything as before.

### Caches and audiences

Lynx shares some work between visitors who see the same pages, such as the menus it stores already built. A rule that implements `VariesByVisitor` says who sees what: `audience()` returns `null` for visitors who see what a guest sees, and a key for each other group. A rule that doesn't implement it makes Lynx treat every visitor separately, which is always correct but shares less.

## Where rules apply

Rules apply to requests from visitors: the site's pages, your own routes and controllers, and Livewire components on the front end. They don't apply:

- in the admin, so editors see every page;
- to the MCP server and AI agents, which work with permissions instead;
- in Artisan commands, queued jobs and the scheduler, so exports, backups, the sitemap and the search index see every page;
- inside `app(PageVisibility::class)->withoutRules(fn () => ...)`, for code of yours that must see everything. The `WithoutPageVisibility` middleware does the same for a whole route.

`lynx()->pages->get()` is never filtered, so a template can always read a page it knows, such as a settings page.

To run code in a console command as a visitor would see the site, for example to warm a cache, wrap it in `app(PageVisibility::class)->asVisitor(fn () => ...)`.
