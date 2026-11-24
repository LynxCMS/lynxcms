---
title: Recipes
description: Common tasks and the section of the docs that shows each one.
order: 5
---

# Recipes

Each entry below names a task and links to the section that shows its code. The code lives on one page only, so it stays tested and current.

## Listing and finding pages

- [List a page's children, or pages from anywhere on the site](/basics/views#listing-pages)
- [Match pages by one condition or another](/basics/selectors#either-one-condition-or-another)
- [Find pages by dates such as "today" or "-30 days"](/basics/selectors#dates)
- [Find pages with at least a number of tags or rows](/basics/selectors#counting)
- [Find pages by the rows of a repeater, their blocks, or their files](/basics/selectors#inside-repeaters-blocks-and-files)
- [Search the text of pages](/basics/selectors#searching-text)
- [Build filters and pagination from the query string](/basics/selectors#filters-and-pagination)
- [Put a visitor's input in a selector safely](/basics/visitor-input#values-in-a-selector)
- [Sort a large site by a translatable field](/basics/selectors#sorting-by-a-translatable-field)
- [See the SQL a selector runs](/basics/selectors#debugging)

## Views

- [Render matrix blocks](/basics/views#matrix-blocks)
- [Let the site's owner edit texts used in every page](/basics/views#editable-template-wide-or-site-wide-texts)
- [Show an edit link to signed-in editors](/basics/views#showing-an-edit-link-to-editors)
- [Design the page not found](/basics/views#the-page-not-found)
- [Read pages in your own routes and controllers](/basics/views#using-your-own-routes)
- [Show responsive images](/basics/images-and-files#showing-images)
- [Offer a different download in each language](/basics/images-and-files#downloads-per-language)
- [Share a private file with a temporary link](/basics/images-and-files#temporary-links)
- [Store files from code](/basics/images-and-files#storing-files-from-code)

## Languages

- [Add a language switcher and hreflang links](/basics/views#languages)
- [Send queued mail in the reader's language](/basics/languages#mail-in-the-reader-s-language)
- [Pass the language to a lazy Livewire component](/basics/views#livewire-components)
- [Use site texts inside Livewire components](/basics/views#texts-in-livewire-components)
- [Tell a translation from a fallback](/basics/languages#telling-a-translation-from-a-fallback)
- [Turn fallback off](/basics/languages#turning-fallback-off)
- [Make an existing field translatable](/basics/templates-and-fields#making-a-field-translatable)

## Saving and publishing

- [Stop a save from overwriting someone else's with `expected_revision`](/basics/publishing#revisions)
- [Publish a page in one language only](/basics/publishing#publishing-in-a-language)
- [Schedule a page to publish or unpublish later](/basics/publishing#scheduled-publishing)
- [Duplicate a page](/basics/publishing#duplicating-a-page)
- [Preview a change before it is written, with a dry run](/deeper/actions-and-hooks#dry-runs)
- [Run an action as a given user](/deeper/actions-and-hooks#acting-as-a-user)
- [Enforce a business rule for every way of saving](/deeper/actions-and-hooks#a-business-rule-for-every-way-of-saving)
- [React when a page is saved, moved, or trashed](/deeper/actions-and-hooks#events)
- [Import many pages without running listeners](/deeper/actions-and-hooks#muting-events)

## Templates and fields

- [Show or require a field only when other fields match](/basics/templates-and-fields#showing-fields-only-when-needed)
- [Limit where pages of a template may go](/basics/templates-and-fields#where-pages-may-go)
- [Create pages that have no URL](/basics/templates-and-fields#pages-without-a-url-and-one-page-templates)
- [Keep the schema in Git and deploy it](/deeper/schema-and-content#deploying)
- [Copy production content to your machine](/deeper/live-site#refreshing-your-content)

## Visitors and accounts

- [Make pages visible to members only](/deeper/visibility#example-members-only-pages)
- [Let visitors sign up](/admin/sign-in#a-site-where-visitors-sign-up)
- [Run a site that sends no email](/admin/sign-in#a-site-that-sends-no-email)
- [Require two-factor authentication](/admin/sign-in#requiring-two-factor-authentication)
- [Protect a page with a password](/modules/page-passwords#setting-up)

## Forms and caching

- [React to form submissions](/modules/forms#reacting-to-submissions)
- [Keep forms working on cached pages](/modules/page-cache#pages-with-forms)
- [Clear cached pages when your own data changes](/modules/page-cache#when-your-own-data-changes)
- [Cache a slow part of a page](/modules/page-cache#fragment-cache)

## Extending the admin

- [Add a screen to the admin](/admin/extending#a-new-screen)
- [Add a button to the page editor](/admin/extending#a-button-in-the-page-editor)
- [Show data from another service on the home screen](/admin/extending#a-panel-with-data-from-another-service)
- [Write your own field type](/deeper/custom-field-types#a-first-field-type-star-ratings)
