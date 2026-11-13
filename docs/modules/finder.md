---
title: Page Finder
description: An admin screen where editors find pages by their fields, template, status and dates, save searches, change the pages found together and export them.
order: 12
---

# Page Finder

The `lynxcms/finder` module adds **Find pages** to the admin: a screen where editors search every page by what it holds, such as courses tagged AI that cost less than 50, or news changed in the last week with an empty summary. Nobody needs to write a [selector](/basics/selectors): the screen builds one from the conditions and shows it, so a developer can copy it into code.

## Setting up

```bash
composer require lynxcms/finder
```

**Find pages** appears in the sidebar under **Pages**, and as a button on the [home screen](/admin/). Press `g` then `q` to open it from anywhere in the admin. Users see it when their role lets them view pages.

## Building a search

A search is made of **conditions**. Each one names what it looks at, how it compares and a value, for example *Price is at least 50*.

Conditions sit in **groups**. A group matches when a page matches **all of these** conditions, or **any of these**. With more than one group, the line above the groups says whether pages must match **every group** or **any group**. One level of groups covers most questions:

| You want | Build |
| --- | --- |
| Courses tagged AI | One group, all of: *Template is Course*, *Topics is AI* |
| Products or case studies tagged AI | Group 1, any of: *Template is Product*, *Template is Case study*. Group 2, all of: *Topics is AI*. Pages match every group. |
| Pages with a price of 50 or more and featured off | One group, all of: *Price is at least 50*, *Featured is off* |
| Pages changed in the last 30 days | One group: *Last changed is in the last days: 30* |
| Pages with no summary yet | One group: *Summary is blank* |

Each condition can look at the page itself, or at any field:

| Looks at | Compares with |
| --- | --- |
| Template, status | is, is not |
| Below the page | is, is not (any page further down the tree, through the page search) |
| Created, last changed, published | in the last days, on the day, before, after, between |
| Text fields | contains, is, starts with, ends with, has every word, is blank, is not blank |
| Number fields | is, is not, less than, at most, more than, at least, between, is blank, is not blank |
| Date fields | on the day, before, after, between, in the last days, is blank, is not blank |
| On and off fields | is on, is off (a page whose switch was never set counts as off) |
| Choice fields | is, is not (one of the field's options), is blank, is not blank. A field that allows several options matches when one of them is the chosen one. |
| Page reference fields | is (any of the chosen pages), is not, has at least, is blank, is not blank |
| Images, files, repeaters and matrices | is not blank, is blank, has at least (items switched off do not count) |

Fields selectors cannot compare, such as passwords, are not offered. Text comparisons use the value visitors see in the admin's current language, with [fallback](/basics/languages), as selectors do.

A condition on a field only finds pages whose template has the field. Without that, **is not**, **is blank**, and **is off** would also match every page of the other templates, which hold no value for it. The selector shows this as a `template=` clause, such as `kind!=report, template=promo|resource`.

A condition that is not complete yet, such as a number field without a number, is left out of the search until it is.

## The results

The results list each page with its template, status and last change, newest change first. **Sort** orders them by title, creation or publishing date instead. Each title opens the page editor.

**Columns** adds up to 6 fields to the table, such as a price or a choice field's option. Each cell shows the value as short text: option labels, page titles, the date, yes or no for a switch, and the first 80 characters of text. Images, files, repeaters, and matrices have no short form, so they are not offered as columns. The columns are part of the search, so its link, a saved copy, and the export keep them.

The finder searches the pages of the page tree, in every status, and leaves out the trash, system pages and the branches modules manage on screens of their own (menus, forms). A user whose role is limited to some branches finds only pages there.

The whole search lives in the address bar, so a bookmark or a link sent to a colleague opens it again.

## Saving searches

**Save search** keeps the search on screen under a name. Saved searches are listed at the top of the screen; choosing one opens it, and saving again updates it (**Save as new** keeps both). Only the user who saved a search, or a superuser, may change or delete it.

A search can be **shared** with everyone who may find pages, for users whose role has the **finder.share** permission. Shared searches run with each viewer's own permissions, so a colleague limited to one branch sees only the pages there.

Saving and deleting are actions, so they also work from code, MCP and the agent:

```php
use LynxCms\Finder\Actions\SaveSearch;

lynx()->run(SaveSearch::class, [
    'name' => 'Courses without a summary',
    'query' => ['groups' => [['conditions' => [
        ['subject' => 'template', 'operator' => 'is', 'value' => 'course'],
        ['subject' => 'summary', 'operator' => 'blank'],
    ]]]],
    'shared' => true,
]);
```

## Changing many pages at once

Tick the results to change them together: **Publish**, **Unpublish**, **Hide**, **Show** or **Move to trash** (after a confirmation). Each page goes through the same action as in the page tree, so a role that may not publish, or a locked page, is refused for that page and the others still change. The message says how many changed and why some did not.

## Exporting

**Export CSV** downloads every page the search finds, not only the page of results on screen: id, title, template, status, last change, address, and the search's columns. A cell that starts like a spreadsheet formula gets a leading quote, so opening the file never runs it.

## The selector

Under the conditions, **Selector** shows the search as a selector, with a **Copy** button:

```text
group1=(template=product), group1=(template=case), topics=12, sort=-modified
```

It runs as it is with `lynx()->pages->find()`. Code finds only published pages unless the selector says otherwise, so add `include=all` to find what the admin finds. Groups of alternatives use selectors' [OR groups](/basics/selectors#either-one-condition-or-another).
