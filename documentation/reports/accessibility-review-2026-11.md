# Accessibility review of the admin, November 2026

Reviewed on 2026-11-19, on `main` after 0.1.52, against WCAG 2.1 level AA. Item 8 of the road to 1.0. Fixes are in the pull request that adds this report (ADR-0136).

## How it was done

1. **Automated checks:** axe-core 4.10.2, with the WCAG 2.1 A and AA rules, on 31 admin screens of the workbench demo site in light and in dark mode, and on the sign-in and password reset screens signed out; then once more with axe's best-practice rules too. `tools/a11y/scan.sh` and `tools/a11y/summary.py` repeat it.
2. **Keyboard checks** in a real browser: the skip link, focus on the main area, the page tree's move mode.
3. **Reading the views** for what tools miss: names of icon buttons, dialogs, live regions, landmarks, headings, error messages and drag and drop.

Screens covered: home, pages (tree, new page under a parent, page editor), fields, templates (list, new, edit), languages, roles, users, account security (its password confirmation), general settings, site texts, search index, menus, forms (inbox and builder), Page Finder, SEO (settings, audit, structured data), sitemap, page cache and static export.

## Result

Before: 6 WCAG rules failing, on every screen. After: none, in light and dark mode; two best-practice findings stay open (below).

| Finding | WCAG | Where | State |
|---|---|---|---|
| The search field carried `aria-expanded`, which a search field may not have | 4.1.2 | Every screen | Fixed |
| The sidebar's `<nav>` sat inside the menu's `<ul>`, so its items were not a list | 1.3.1 | Every screen | Fixed |
| Language tabs pointed to panels that had no role or id | 4.1.2 | Fields, templates, page editor, menus, media, site texts | Fixed |
| The template layout's tab list held remove and add buttons, which a tab list may not | 4.1.2 | Templates | Fixed: a group of pressed buttons |
| Hidden pages in the tree were dimmed to 2.9:1 | 1.4.3, 1.4.1 | Pages | Fixed: a muted colour at 4.8:1, and an eye icon named "Hidden" so colour is not the only sign |
| Primary-coloured text (links, "Add tab", "New field") read at 2.4:1 in dark mode | 1.4.3 | Dark mode | Fixed |
| A site's light accent was reused unchanged in dark mode | 1.4.3, 1.4.11 | Branded sites | Fixed: lightened to 4.5:1 |
| No skip link past the sidebar and header | 2.4.1 | Every screen | Fixed |
| Two `<main>` landmarks: BlatUI's sidebar inset and the layout's own | 1.3.1 | Every screen | Fixed: the inset is a `<div>` |
| Notices were added as new live regions, which screen readers may not announce | 4.1.3 | Every screen | Fixed: the notice area is a live region from the start |
| Search results were shown without telling screen readers | 4.1.3 | Every screen | Fixed: the count is announced |
| Error messages are not tied to their fields with `aria-describedby` | 1.3.1, 3.3.1 | Every form, about 130 messages | Open |
| Notices disappear after 4 to 9 seconds | 2.2.1 | Every screen | Open, low |
| The sidebar's brand and account menu sit outside any landmark (axe best practice `region`) | none | Every screen | Open, moderate |
| Two navigations named "Breadcrumb" (axe best practice `landmark-unique`) | none | Page editor, new page | Open, moderate |

One reported failure was not real: the brand name on the sign-in screens in dark mode. The page is dark (`color-scheme: dark`), but its background is translucent, and axe assumed white behind it. The admin now gives `<html>` an opaque background, so checkers measure what people see.

## Open

* **Errors tied to their fields.** Fields with an error have `aria-invalid`, and the message shows next to the field with `role="alert"`, so it is announced when it appears. But moving back to the field later, a screen reader says "invalid" without the reason. Each message needs an id and its field an `aria-describedby`; a shared error component would do it for all of them.
* **Landmarks.** The sidebar's brand and account menu belong in a landmark, which needs BlatUI's sidebar markup changed; and the page editor's second breadcrumb needs its own name.
* **Notices that pause.** Errors stay next to their fields, so nothing is lost when a notice goes, but a notice should stay while the pointer or the focus is on it.

## Checked and found sound

* Dialogs trap the focus, close with Escape and are modal (`aria-modal`).
* The page tree has a keyboard move mode (Move, then the arrow keys, Enter to finish), so moving pages needs no dragging (2.5.7).
* Icon buttons have accessible names; axe found none without.
* Each screen has one `<h1>` and, after the fix above, one `<main>`, and a named navigation landmark; the document language follows the admin's language.
* Focus rings are visible on links, buttons and fields.

## Not covered

Screen readers themselves (VoiceOver, NVDA), zoom to 400% and reflow on narrow screens, the rich text editor's toolbar, the forms results screen, and modules' screens beyond those listed. A screen reader pass by a person who uses one daily is the next step before 1.0.
