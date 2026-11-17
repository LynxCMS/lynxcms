---
title: Selector Cheat Sheet
description: Every selector clause, operator and option on one page, with an example of each.
---

# Selector Cheat Sheet

Selectors find pages. This page lists their syntax with one example per line. [Finding Pages](/basics/selectors) explains each part.

## Running a selector

| Code | Returns |
| --- | --- |
| `lynx()->pages->find('template=post')` | Every match, as a `PageArray`. |
| `lynx()->pages->findOne('template=post, sort=-published')` | The first match, or `null`. |
| `lynx()->pages->get('/about')`, `get(42)` | One page by path, id or selector, or `null`. |
| `lynx()->pages->count('template=post')` | How many pages match. |
| `$page->children('sort=title')` | The page's children, with an optional selector. |
| `lynx()->language('pt', fn () => ...)` | Runs the code in another language. |
| `php artisan lynx:find "template=post" --lang=pt --sql` | Runs a selector from the terminal and prints its SQL. |

## Syntax

| Selector | Means |
| --- | --- |
| `template=post, price<300` | Commas join clauses. Every clause must match. |
| `template=post\|news` | `\|` between values: either value. |
| `category!=news\|events` | `!=` with several values: none of them. |
| `title\|summary%=risk` | `\|` between fields: any of the fields matches. |
| `summary=` | Empty value: the field is empty or missing. |
| `price!=` | Not empty: the field has a value. |
| `title="Risk, explained"` | Double quotes keep commas, pipes and parentheses as text. |
| `(price<20), (price>=50, summary%=advanced)` | Groups in parentheses: at least one group matches. Inside a group, commas still mean "and". |
| `cost=(price<20), cost=(price>70)` | Named groups: groups with the same name form a set, and every set must match. |

Groups go one level deep. `sort`, `limit`, `start`, `include`, `join`, `language` and `search` act on the whole selector and stay outside groups.

## Operators

| Operator | Matches | Example |
| --- | --- | --- |
| `=` | Equal | `template=post` |
| `!=` | Not equal | `template!=page` |
| `<` `>` `<=` `>=` | Smaller, greater | `price<300` |
| `%=` or `*=` | Contains | `title%=risk` |
| `~=` | Contains every word, in any order | `summary~="risk management"` |
| `^=` | Starts with | `title^=How` |
| `$=` | Ends with | `name$=-2026` |

The text operators ignore case.

## Page properties

| Clause | Finds |
| --- | --- |
| `template=post` | Pages of a template. |
| `parent=/blog` | Pages directly below a page, by path or id. |
| `has_parent=/projects` | Pages anywhere below a page. |
| `id=42`, `id!=1\|2` | Pages by id. |
| `name=about` | Pages by URL name in the current language. |
| `created>=-7 days` | Pages created in the last week. |
| `modified<-30 days` | Pages not saved for a month. |
| `published>=2026-01-01` | Pages first published since a date. |

## Dates

| Clause | Finds |
| --- | --- |
| `starts_on>=today` | From today on. Any words PHP reads as a date work. |
| `published_on>=2026-01-01, published_on<2027-01-01` | Within a year. A date without a time means midnight. |
| `modified<-7 days` | Before a moment relative to now. |

## Counts, items and files

| Clause | Finds |
| --- | --- |
| `children.count>0` | Pages with children (hidden and unpublished ones count, drafts and system pages don't). |
| `related.count>=2` | Pages with at least two page references in a field. |
| `gallery.count=0` | Pages without images in a field. |
| `credits.role%=direction` | Pages with a repeater item that matches. Only items switched on count. |
| `credits.partner!=/partners/acme` | Pages where no item matches. |
| `blocks.type=video` | Pages with a matrix block of a type. |
| `gallery.tags=hero` | Pages with a file or image that matches: `file`, `mime`, `bytes`, `tags`, `description`. |
| `hero.width>=1600` | Images by size: `width`, `height`. |
| `blocks.gallery.count>0` | Files inside matrix blocks. |

## Search

| Clause | Finds |
| --- | --- |
| `search*="opening hours"` | Pages whose title or searchable fields hold every word, each as a word beginning. Also written `search%=` and `search~=`. |
| `search*="estaç"` | Word beginnings: finds "estações". Case and accents are ignored. |
| `search*="livros"` | Singular and plural forms, in the [languages with rules](/basics/languages#search-in-your-languages): finds "livro". |
| `search*=seasons\|estações` | Either search. Inside double quotes a pipe is plain text, so alternatives go unquoted. |

## Languages

| Clause | Finds |
| --- | --- |
| *(none)* | Values in the request's language, with fallback. |
| `language=pt` | Values in another language (also `lang=pt`). |
| `language!=pt` | Pages not yet published in a language. |

## Status and versions

| Clause | Finds |
| --- | --- |
| *(none)* | Published pages that aren't hidden, with a URL. |
| `include=hidden` | Hidden pages too. |
| `include=unpublished` | Unpublished pages too. |
| `include=no_url` | Pages whose template has no URL too. |
| `include=all` | All of the above. |
| `status=unpublished`, `status=hidden\|unpublished`, `status=locked` | Only pages with that status. |
| `status!=locked` | Leaves locked pages out. |
| `has_draft=1` | Pages with a draft waiting to be published. `=0` finds the others. |
| `has_schedule=1` | Pages with a publish or unpublish date waiting. |
| `has_outdated=pt` | Pages with a machine translation into Portuguese whose source has changed. |

## Order, paging and loading

| Clause | Does |
| --- | --- |
| `sort=title` | Sorts ascending by a field or by `name`, `id`, `created`, `modified`, `published`. |
| `sort=-published` | Sorts descending. |
| `sort=-published, sort=title` | Sorts by each in turn. |
| *(no sort)* | The editors' order among siblings (`sort=sort`), then id. |
| `limit=10` | At most ten pages. |
| `start=20` | Skips the first twenty, for paging. |
| `join=summary\|price` | Loads these fields with the pages, in the same query. |
| `join=all` | Loads every field with the pages. |

## Visitors' input

| Code | Does |
| --- | --- |
| `find($request->string('q'), maxLimit: 50, defaultLimit: 20)` | Caps a selector that comes whole from a request. |
| Visitor's text inside double quotes, with their double quotes removed | Keeps the text from adding clauses of its own. See [Visitor Input](/basics/visitor-input#words-in-a-selector). |
