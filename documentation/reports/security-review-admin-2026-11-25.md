# Security review of the admin package, 2026-11-25

Reviewed on 2026-11-25, on `main` at `8a1439d8`. The review was read-only: no code changed, no test ran, and no finding was exercised in a browser. Every finding below was traced in the code, and the file and line are given so each can be checked.

## Scope

Covered: `packages/admin` (`src`, `routes`, `config`, `resources/views`, `resources/js`). That is about 17,000 lines of PHP, 8,400 of Blade and 900 of the admin's own JavaScript. Three passes ran side by side:

1. Routes, controllers, middleware, the service provider, sign-in and account code (`src/Auth`), branding and user creation.
2. Every Livewire component and its helpers (`src/Livewire`, `Editor`, `Tree`, `References`, `Home`): where each one authorizes, which properties the browser can change, and what each read returns.
3. Views and JavaScript: raw output, values inside Alpine and Livewire expressions, DOM sinks, headers.

Not covered: `blatui-core.js` beyond a search for DOM sinks, the built files in `dist/`, the internals of the schema forms (`FieldForm`, `TemplateForm`, `LanguageForm` and their concerns, all behind `schema.manage`), the converters in `Editor/Converters` other than `PageConverter`, components of other packages that embed the admin's tree, and testing against a running server. `src/Auth` was read for routes, limits and permissions only: the earlier report (`security-review-2026-11.md`) went through sign-in, two-factor authentication, temporary passwords and invitations, and its fixes are in place.

## Summary

No way was found for a user to write outside their rights, and no cross-site scripting that crosses from one user to another. Every one of the 29 Livewire components authorizes in `boot()`, which runs on every request, and every page id they hold is locked.

What was found is reads. Three screens show titles and addresses of pages outside the role's branches or templates, unpublished ones included.

| Id | Severity | Finding | Where |
|---|---|---|---|
| M1 | Medium | A page reference field shows the title of any page id put into it | `Editor/Converters/PageConverter.php:44`, `Editor/PageChoices.php:81` |
| M2 | Medium | The create screen shows the title and address of any parent page | `Http/CreatePageController.php:19` |
| M3 | Medium | The trash confirmation lists referencing pages from outside the role's scope | `Editor/ReferenceWarning.php:24` |
| L1 | Low | "Empty trash" reports titles of trashed pages from every branch | `Livewire/Concerns/PageQuickActions.php:129` |
| L2 | Low | Account security actions skip the password confirmation on Livewire updates | `AdminServiceProvider.php:138` |
| L3 | Low | The admin sets no framing or content security headers | whole package |
| L4 | Low | The admin's searches ignore a field's view access | `Livewire/AdminSearch.php:57`, `Editor/PageSearch.php:39` |
| L5 | Low | The editor's preview prints an `html` value without cleaning it | `resources/views/components/editor/field.blade.php:90` |
| L6 | Low | Names inside Alpine expressions rely on validation done elsewhere | several views |

## Findings

### M1. A page reference field resolves any id

A page reference value is a list of page ids. Two places turn ids into titles without asking whether the user may view the page:

- `PageConverter::display()` (`Editor/Converters/PageConverter.php:44`) runs `find('id=…, include=all')` on whatever ids it is given.
- `PageChoices::options()` (`Editor/PageChoices.php:81`) loads every chosen id its scoped list does not hold, also with `include=all`. The comment explains why: a page chosen earlier must stay visible so that saving keeps it.

The ids come from `PageEditor`'s `values` property, which the browser can change. In the core, `PageReferenceType` checks how many ids a value has, never whether they exist or whether the actor may see them.

**Who:** an editor with `pages.edit` on one page whose template has a page reference field. A role limited to one branch or template is enough.

**Effect:** the editor puts page id `N` into the field. "Review changes" shows its title, as does a select, radio or checkbox input, with no save needed. After a save, `PagePicker` also shows its address and its parent's title. Hidden, unpublished and trashed pages are included. A missing title means the id does not exist, so ids can be walked one by one.

**Fix:** resolve titles only for ids the actor may view (`AdminAccess::viewable()`) or that the page's stored value already holds. For the rest, show the id without a title. Better still, have `ValueWriter` refuse a newly added id the actor may not view, which also closes the same path over MCP and the API.

### M2. The create screen shows any parent

`CreatePageController` (`Http/CreatePageController.php:19`) checks that the page in `?parent=` exists and that its template allows children. It does not check `pages.view` or `pages.create`, and neither does `PageCreator` in `boot()` or `mount()` (`Livewire/PageCreator.php:51`, `:57`). The screen prints the parent's title (`:91`) and, once a title is typed, the new page's address built from the parent's (`:114`).

**Who:** anyone who can open the admin, whatever their role.

**Effect:** `pages/create?parent=N` gives the title and address of page `N`, for unpublished, hidden and trashed pages too. The create itself is refused by the action.

**Fix:** require `pages.create` on the parent in the controller and in `PageCreator::mount()`, and answer 403 otherwise.

A related detail: the browser can set `template` to any template name, and the screen then renders that template's fields (labels, notes, settings) before `create()` refuses it. Checking the template against the offered choices when it changes closes that.

### M3. The trash confirmation lists pages outside the scope

Before trashing, the admin warns which pages link to what is about to go. `ReferenceWarning::for()` (`Editor/ReferenceWarning.php:24`) asks `lynx()->pages->backlinks()` for the whole branch and returns the count and the first three pages with title, template and parent title. Nothing filters them by the actor's `pages.view` scope.

`PageChildren::confirmTrash()` is a public method (`Livewire/Concerns/ChangesPages.php:56`). Its only check is that each id is a child of the page being edited (`Livewire/Concerns/ChangesChildren.php:48`). No action runs before the read, so no permission to trash is asked either.

**Who:** an editor who can open a page's Children tab and whose scope is narrower than the site.

**Effect:** titles and parent titles of pages in other branches that link into theirs, unpublished ones included.

**Fix:** filter the referencing pages with `AdminAccess::viewable()`, show the rest as a count only, and require the right to trash each child before building the warning. The core report lists the same gap for `linked_from` in page history (L7 there): one scoped `backlinks()` would serve both.

### L1. "Empty trash" names pages from every branch

`EmptyTrash` works out which trashed branches hold locked pages before it authorizes (`core/src/Actions/Pages/EmptyTrash.php:65`), for every branch in the trash. `PageQuickActions::report()` (`Livewire/Concerns/PageQuickActions.php:129`) then loads the title of each with `pages->get()`, which no scope filters. An editor with `pages.delete` on one branch sees titles of other branches' trashed pages that contain a locked page.

**Fix:** authorize first in the action, so pages the actor may not delete are reported as refused, without a title. Or filter what `report()` names with `viewable()`.

### L2. Account security without the password confirmation

The account security screen sits behind `password.confirm` on its route (`Auth/AdminAuth.php:186`). Livewire runs only its persistent middleware on updates, and that list (`AdminServiceProvider.php:138`) holds `AuthenticateSession`, `RequireOwnPassword` and `RequireTwoFactor`, not the password confirmation. So once the screen is open, these calls need no password however long ago it was confirmed (`Livewire/Concerns/ManagesTwoFactor.php:71`, `:79`, and `ManagesPasskeys`):

- `regenerateRecoveryCodes`, which returns the new codes;
- `enableTwoFactor`;
- `disableTwoFactor`, unless the admin requires two-factor authentication;
- `deletePasskey`.

Changing the password still asks for the current one.

**Who:** someone at an unattended computer with the tab open, or holding a stolen session together with the page's state.

**Fix:** add Laravel's `RequirePassword` middleware to the persistent list for this component, or check `password.confirm`'s session timestamp inside these methods. This is the same class of gap the earlier review fixed for the two-factor and temporary password checks.

### L3. No framing or content security headers

Nothing in the admin sets `X-Frame-Options`, `Content-Security-Policy` or `Referrer-Policy`. The only CSP in the package is on uploaded SVG logos. Another site can therefore load the admin in a frame and trick a signed-in user into clicking its buttons (delete, publish, invite).

**Fix:** a middleware on admin and sign-in routes that sends `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'` and `Referrer-Policy: same-origin`. The page preview in the admin, if it frames the site's own pages, needs `'self'` on those. A full script policy is harder, since Alpine evaluates inline expressions, and nothing found here calls for it.

Related, and not verified: the temporary password a superuser gives (`livewire/user-roles.blade.php:11`), recovery codes and the two-factor secret are rendered into the page. They appear in Livewire responses, which browsers do not cache. Sending `Cache-Control: no-store` on admin responses would settle the question for full pages too.

### L4. Searches ignore a field's view access

`AdminSearch` accepts a selector (`Livewire/AdminSearch.php:57`), and `PageSearch` builds a `search*=` clause (`Editor/PageSearch.php:39`). Results respect the role's page scope. Neither asks `FieldAccess` about the fields a clause names, so `salary>50000` or `notes^=ab` answers a question about a field the editor may not view. The core report describes the same gap for the agent toolkit (M3 there). The fix belongs in the selector compiler and covers both.

### L5. The editor's preview is not cleaned

A field with formatters shows a "Preview" in the editor (`resources/views/components/editor/field.blade.php:90`), printed raw from `lynx()->formatters()->render()`. The comment says "cleaned by core". For a field whose format is `html`, `FormatterChain` cleans only when one of its formatters produces HTML (`core/src/Fields/FormatterChain.php:70`). With the others, the value is printed as it is.

The value is the editor's own unsaved input, so this is script a user runs on themselves. It reaches someone else in two cases: the format change described in the core report (M2 there), and a value written to the database without passing through the field type. The review screen beside it already cleans what it shows (`components/editor/review.blade.php:35`).

**Fix:** clean in the `html` branch of `FormatterChain::base()`, which fixes this preview and the core finding with one change.

### L6. Names inside Alpine expressions

Several views put a name between quotes inside an Alpine or Livewire expression: `'{{ $field->name }}'` (`livewire/field-list.blade.php:49`, and through `$model` in `inputs/textarea.blade.php:7`), language codes (`choices-editor.blade.php:22`, `editor/subfield.blade.php:29`), template and role names in `role-form.blade.php`. Blade escapes the value for HTML, and the browser undoes that before Alpine reads the attribute as JavaScript. What keeps these safe is the pattern each name must match when it is created.

Those patterns end in `$`, which accepts a trailing newline (core report, L5). A newline inside a JavaScript string is a syntax error, so the result is a broken screen, not script. Still, the safety of these views rests on rules in another package.

**Fix:** use `@js($name)` at these places, as about a hundred other places in the views already do.

## Observations

- **Duplicating pages.** `DuplicatesPages::duplicate()` on the tree and `PageSettings::duplicate()` call the action with no check of their own, and the action checks no scope (core report, H1). Fixing the action fixes both.
- **Screens without a route check.** `fields`, `templates`, `site-texts`, `search-index`, `page-references` and `pages` are plain `Route::view` routes, and `EditFieldController` and `EditTemplateController` check only that the item exists. Their Livewire components refuse in `boot()`, so a user without the permission gets a 403 from the component. A check in the route would refuse before the layout renders.
- **Search index screen.** `SearchIndexScreen` looks up any page and lists its indexed words, whatever the scope or field access. It needs `search.manage`, which therefore amounts to reading all searchable text.
- **Broken links report.** `ReportRows::broken()` resolves the title of a link's target without the scope (`References/ReportRows.php:124`). The referencing page is scoped.
- **Field conditions.** `PageEditor::conditions()` reads saved values of fields the user may not view, to decide which conditional fields show. Which fields appear can then reveal such a value.
- **Rights taken away mid-session.** Components that hold a locked page id never ask `pages.view` again. An editor whose grant is removed keeps reading an open page until they navigate. Writes are checked by the actions.
- **User search.** `EloquentUsers::search` chains `orWhereRaw` at the top level of the query, so a global scope on the user model, such as soft deletes, stops applying to the second half. Superusers only, and values are bound.
- **`target="_blank"` without `rel="noopener"`** in `page-table-input.blade.php:20` and `partials/items/subfields.blade.php:33`. Both lead to admin pages, and current browsers imply it.
- **Icon names.** Some views build `lucide-{name}` component names from a field's `icon` setting without asking `IconSet::has()`. An unknown name is a 500 on the editor.

## Checked and found sound

- **Routes.** Every admin route carries `auth`, `AuthenticateSession`, the `accessLynxAdmin` gate, `RequireOwnPassword` and `RequireTwoFactor`. Superuser screens check in the controller and again in each component.
- **Livewire components.** All 29 authorize in `boot()`. Page, user, role, field and template ids are locked. Child components get their page id from the server's render.
- **Page editor.** Fields the user may not view are left out. Only editable fields are sent on save, so a value for a hidden or read-only field is dropped before the action. Revision compare and draft review skip them too.
- **Tree, home, children, link dialog, page picker.** Lists pass the role's scope to the finder. `LinkDialog` asks `viewable()` again for every id the browser sends.
- **Selectors from search boxes.** Text is stripped of `"` and NUL and placed inside double quotes, which the parser cannot be broken out of. Other parts are integers, validated codes or fixed keys.
- **User management.** Inviting, creating, temporary passwords, resetting two-factor and assigning roles are actions without a permission, so superusers only, and none is exposed as a tool. A temporary password cannot be given to oneself. The last superuser cannot be removed through role assignment.
- **Sign-in.** Limits per account and address and per account alone, a limit on two-factor attempts and on passkeys. Invitations use Laravel's password broker with hashed tokens, expire, and their routes are limited.
- **Branding.** Colors must match `#rrggbb` on save and again on read, and nothing else reaches the `<style>` block. Logos are recognized by their bytes, named by their hash, and SVGs are served with a sandboxing CSP and `nosniff`.
- **Views.** Every `{!! !!}` was traced: translated strings with escaped variables, `Js::from` output, validated branding CSS, Fortify's QR code, and cleaned HTML in the review screen. No `x-html` anywhere. No link takes a stored free-text address.
- **JavaScript.** No `innerHTML`, `eval`, `document.write`, `window.open` or message listener in the admin's own scripts. The rich text editor (Tiptap) loads content through its schema, and its link extension refuses `javascript:` addresses. The HTML source view is a textarea.
- **Forms.** Every form that is not Livewire's has a CSRF token.
- **SQL and redirects.** No raw SQL with a value from the browser, and every redirect target comes from the server.

## Suggested order

1. M2. Two lines in the controller and the component.
2. M1 and M3 together with core L7: one helper that resolves page titles for an actor, used wherever ids from the browser or from backlinks become titles.
3. L2. One entry in the persistent middleware list, with a test like the one for two-factor.
4. L3. One middleware.
5. L5 with core M2, then L6 as a sweep over the views.
6. L1 and L4 with their core counterparts.

## Resolution

Fixed on 2026-11-26 on the branch `fix/admin-security-review`, one commit per finding, each with a test that fails without the fix.

| Id | Fix |
|---|---|
| M1 | `pages.save` refuses a newly added page reference (page field, page subfield, rich text link) to a page outside the actor's `pages.view` scope, or one that does not exist (`ReferenceChecks`, ADR-0154). Pages the field already holds are kept. The admin resolves titles only for viewable pages and shows others as "Page #N (outside your access)". The training guide's course manager now views `/locations/`. |
| M2 | The New page screen needs `pages.create` on the parent for one of the offered templates (`TemplateChoices::creatable()`), in the controller and in `PageCreator::mount()`. It offers only those templates, and a template the browser sets outside them goes back to the preferred one before its fields render. |
| M3 | `ReferenceWarning` names only referencing pages the user may view and counts the rest. `confirmTrash()` runs a dry run of the trash for each page first. |
| L1 | `EmptyTrash` authorizes each branch before it looks for locked pages, so a branch the actor may not delete is only counted as denied. |
| L2 | The account security actions (two-factor on, confirm, off, recovery codes, removing a passkey) check the password confirmation as `RequirePassword` does, and send the user to confirm it again once it expired (`ConfirmsPasswordAgain`). A check inside the component was chosen over the persistent middleware, which would answer Livewire with a 423 error modal. |
| L3 | `SendAdminHeaders` on admin, sign-in and invitation routes: `X-Frame-Options: SAMEORIGIN`, `Content-Security-Policy: frame-ancestors 'self'`, `Referrer-Policy: same-origin`. A response with its own policy keeps it. |
| L4 | The search box and the page pickers run under `SelectorPolicy::forActor()`. While the index holds a field the user may not view, plain text searches titles only. |
| L5 | The editor's preview goes through the HTML cleaner. Stored values are cleaned on save, so the visitors' render is unchanged. |
| L6 | Values inside Alpine and Livewire expressions go through `@js()`, or `Js::from()` inside component tags, in every package. `tests/Unit/EchoInScriptTest.php` refuses the old pattern. |

Not changed: the observations, and `Cache-Control: no-store` on admin responses (L3's unverified note).
