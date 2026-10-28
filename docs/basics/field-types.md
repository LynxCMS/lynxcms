---
title: Field Types
description: Every field type, its settings, and how its values read in templates and selectors.
---

# Field Types

A field's **type** decides what it stores, how editors fill it in, and what your templates read. This page lists every type, with its settings. [Templates and Fields](/basics/templates-and-fields) explains how to create fields and attach them to templates.

Settings go in the field's settings panel in the admin (**Settings › Fields**), or in the `settings` array in code:

```php
lynx()->fields->add('price', 'decimal', settings: ['places' => 2, 'min' => 0]);
```

`lynx()->fields->types()` returns every type with its description and settings, which is also what the MCP server and the AI agent read.

## Shared and translatable

Text, textarea and URL fields can be **translatable**, holding one value per language. Every other type is **shared**: one value for every language. A date is the same date in English and Portuguese, and a page reference points at the same page. Where a shared type has text of its own, such as a choice's labels or an image's description, that text is per language.

## Available types

| Group | Types |
| --- | --- |
| Text | [`text`](#text), [`textarea`](#textarea), [`url`](#url), [`email`](#email) |
| Numbers and dates | [`integer`](#integer), [`decimal`](#decimal), [`date`](#date), [`time`](#time), [`toggle`](#toggle) |
| Choices | [`choice`](#choice) |
| Other pages | [`page`](#page), [`page_table`](#page-table) |
| Groups of fields | [`repeater`](#repeater), [`matrix`](#matrix) |
| Media | [`images`](#images), [`files`](#files) |
| From modules and apps | [`icon`](#icon), [`component`](#component), [`options`](#options), [`password`](#password) |

## Text

### `text`

A single line of text up to 255 characters, such as a title or a name. Can be translatable.

| Setting | Meaning |
| --- | --- |
| `max_length` | Most characters, up to 255. |
| `placeholder` | Hint shown in the empty input. |

Templates read a text value that prints as the text, or `null` when it is empty, so `@if ($page->subtitle)` works. Print it with `@field($page->subtitle)` to mark text that comes from another language.

### `textarea`

Longer text. Can be translatable.

| Setting | Meaning |
| --- | --- |
| `format` | `plain` (default), `html` for a rich text editor, or `markdown`. |
| `max_length` | Most characters. |
| `formatters` | Formatters to run when the value is shown, in order, such as `['markdown', 'smartypants']`. |

With `format: html`, editors get a rich text editor, and Lynx cleans the HTML on every save: it keeps a safe set of tags and attributes, and refuses links that could run script.

**Formatters** change how text is shown, never what is stored. `heading_anchors` gives headings an `id` made from their text (`Opening hours` becomes `#opening-hours`), so links can point at a section. `$page->getUnformatted('body')` returns the stored value.

The rich text editor's **link dialog** links to an address, an email, a page found by its title, a section of a page (its headings, when the field uses `heading_anchors`) or a file of the page. Its Attributes tab sets the link's title, target and relations (`nofollow`, `sponsored`, `noreferrer`; add `ugc` in `lynx-admin.links` for sites whose visitors write content). Links that open a new window get `noopener`.

### `url`

A web address starting with `http://` or `https://`, so a link can never run script. Can be translatable, for sites that link to a different page per language.

| Setting | Meaning |
| --- | --- |
| `relative` | Also accept a path on the site, such as `/contact`. Default `false`. |

### `email`

An email address, checked on save, with the domain stored in lower case. Templates read a string.

## Numbers and dates

### `integer`

A whole number, such as a count, a duration or a price in cents.

| Setting | Meaning |
| --- | --- |
| `min`, `max` | Smallest and largest allowed values. |

### `decimal`

A number with decimals, such as a price or a rating. Templates read a float. Editors may type a comma as the decimal point.

| Setting | Meaning |
| --- | --- |
| `places` | Digits after the decimal point, 0 to 8. Default 2. |
| `min`, `max` | Smallest and largest allowed values. |

### `date`

A date, or a date and a time. Templates read a `CarbonImmutable`, or `null` when empty:

```blade
{{ $page->starts_on?->isoFormat('D MMMM YYYY') }}
```

| Setting | Meaning |
| --- | --- |
| `time` | Also store the time of day. Default `false`. |

Selectors compare dates, and understand words PHP reads as dates: `starts_on>=2026-01-01`, `starts_on>=today`, `starts_on<-7 days`. A value without a time means midnight.

### `time`

A time of day without a date, such as an opening hour. Stored and read as `HH:MM`, or `HH:MM:SS` with `seconds: true`. Editors may type `9h30` or `9.30`. Selectors compare times: `opens<12:00`.

### `toggle`

On or off. Templates read `true` or `false`.

## Choices

### `choice`

One of a fixed list of options, or several with `multiple: true`. Each option has a **value**, which pages store, and a **label** per language, which visitors read. Labels can change and be translated freely; the stored values stay.

| Setting | Meaning |
| --- | --- |
| `options` | The options, each with a `value` and a `label`. A missing value is made from the label. |
| `multiple` | Allow several options. Default `false`. |
| `input` | How editors choose: `select` (default for one), `radios` or `checkboxes` (default for several). |
| `default` | The option new pages and new items start with: a value, or a list of values with `multiple`. |

```php
lynx()->fields->add('level', 'choice', settings: [
    'input' => 'radios',
    'options' => [
        ['value' => 'beginner', 'label' => ['en' => 'Beginner', 'pt' => 'Iniciante']],
        ['label' => ['en' => 'Advanced', 'pt' => 'Avançado']], // value "advanced"
    ],
]);
```

With a `default`, a new page or a new repeater or matrix item starts with that option chosen, and editors can change it. Lynx stores the default when the page or item is created, so `padding=medium` finds those pages too. Pages that existed before the default was set keep their empty value, so a template should still handle `null`. In the admin, tick **Default** next to an option in the field's options.

A single choice reads as a `Choice`, or `null`. It prints its label in the current language, and `->value` gives the stored value. Several choices read as a `ChoiceList`, with `has()`, `values()` and `labels()`:

```blade
<p>Level: {{ $page->level }}</p>

@if ($page->level?->value === 'advanced')
    <p>Some experience expected.</p>
@endif

@foreach ($page->formats as $format)
    <span>{{ $format->label() }}</span>
@endforeach
```

Selectors compare values: `level=advanced`, `formats=online|hybrid`. Removing an option leaves the pages that had it without a choice.

To build filters, tabs or menus from a field's options, list them all with `lynx()->fields->choices()`. It returns the options in order as a `ChoiceList`, with labels in the current language:

```blade
@foreach (lynx()->fields->choices('level') as $level)
    <a href="?level={{ $level->value }}">{{ $level }}</a>
@endforeach
```

## Other pages

### `page`

References to other pages, such as related courses or a post's category.

| Setting | Meaning |
| --- | --- |
| `multiple` | Allow more than one page. Default `true`. |
| `min`, `max` | Fewest and most pages, such as at most 8 cards on a home page section. `min` counts once editors pick any; `required` asks for at least one. |
| `templates` | Templates the chosen pages may use. |
| `input` | `search` by title (default), or `select`, `radios` or `checkboxes` listing the allowed pages (up to 100), which suits short lists such as categories. |

Several pages read as a `PageArray`; one page (`multiple: false`) reads as that page or `null`:

```blade
<p>Category: {{ $page->category?->title }}</p>

<p>Related: {{ $page->related->implode(', ', 'title') }}</p>
```

Selectors take a page's path or id: `category=/categories/news`, `related=42`. `related.count>=2` counts the references.

### `page_table`

Pages made for this page and listed in a table, such as a course's sessions or an event's speakers. In the admin, editors add an item by its title, then edit it as a page of its own. New items are hidden, so they stay out of menus and lists.

| Setting | Meaning |
| --- | --- |
| `templates` | Templates of the items. Required. |
| `parent` | Path new items are created under. Default: the page itself. |
| `columns` | Fields the admin table shows. Default: the title. |
| `remove` | Whether removing an item from the table keeps its page (`keep`, default) or moves it to the trash (`trash`). |

Templates read a `PageArray`, in the editor's order. Selectors compare ids, like page references.

## Groups of fields

### `repeater`

A list of items that each have the same fields, such as a film's credits, each with a partner and a role. Editors sort items and can switch one off without deleting it.

| Setting | Meaning |
| --- | --- |
| `fields` | The fields of each item, in order. They are existing fields, each with optional `required`, `label`, `description`, `notes` and `width`. |
| `min`, `max` | Fewest and most items. |
| `max_depth` | Levels items may be indented, 0 to 5. Default 0. |
| `item_label` | Header of each item in the admin, such as `{partner} · {role}`. |
| `add_label` | Text of the add button. |
| `collapse` | Which items start open in the admin. |

Each item lists fields of the site, so create them first:

<<< @/snippets/field-types/repeater.php#fields{php}

In the admin, the repeater's field screen opens on its fields, in order, each with **Required** and a width (Half, Third, Full…). Search for an existing field to add it, or create a new one on the spot. The **Preview** tab shows an item as editors see it, and **Limits and display** sets how many items, the add button's text in every language and whether items start open.

Templates loop over the items that are switched on, in order. Each item reads its fields like a page does:

```blade
@foreach ($page->credits as $credit)
    <p>{{ $credit->partner?->title }}: @field($credit->role)</p>
@endforeach
```

Translatable fields inside an item keep one value per language and fall back per field. To save items from code, give each one its `values`; pass back an item's `key` to update it, and leave it out for a new item:

```php
$page->credits = [
    ['values' => ['partner' => [7], 'role' => ['en' => 'Direction', 'pt' => 'Realização']]],
    ['enabled' => false, 'values' => ['partner' => [9], 'role' => 'Editing']],
];
$page->save();
```

Selectors look inside items with a dot: `credits.role%=direction` finds pages where an item that is switched on matches.

### `matrix`

Typed content blocks: each item has a **type**, and the fields of that type. Use it for page builders, such as a page made of text, image, video and quote blocks.

| Setting | Meaning |
| --- | --- |
| `types` | The block types, each with a `slug`, a `label` and its `fields`. Slugs never change, so reordering types or renaming them never changes what a block is. |
| `min`, `max`, `max_depth`, `item_label`, `add_label`, `collapse` | As for repeaters. |

A block type lists fields of the site, the same way a template does. Create the fields first, then the matrix:

<<< @/snippets/field-types/matrix.php#fields{php}

A field can appear in several block types and in templates too, such as `body` here. Listing a field that does not exist yet fails with a message naming the call that creates it.

In the admin, the matrix's field screen opens on its block types, one under the other. Open one to set its name and add its fields, from the fields you have or by creating one on the spot; drag and drop orders types and fields. The **Preview** tab shows every block type as editors see it, with the add buttons, and **Limits and display** holds the settings for the whole field.

A template can offer only some types: `['name' => 'blocks', 'allowed_types' => ['text']]`. A block that changes type keeps its other values, hidden. [Views](/basics/views#matrix-blocks) shows how to render blocks with `@matrix`. Selectors match types with `blocks.type=video`.

## Media

These types come with the `lynxcms/media` package. [Images and Files](/basics/images-and-files) covers uploading, sizes and templates in full.

### `images`

Images that belong to the page, sortable, each with a focus point, a description per language and tags.

| Setting | Meaning |
| --- | --- |
| `max_files` | Most images; `1` makes templates read one image instead of a list. |
| `extensions` | Allowed extensions. Default `jpg jpeg png webp gif avif`. |
| `max_kb` | Largest upload, in kilobytes. Default 10240. |
| `min_width`, `min_height` | Smaller images are refused. |
| `max_width`, `max_height` | Larger images are scaled down on upload. Default 2560. |
| `tags` | Tags editors may choose; empty allows any. |

### `files`

Documents that belong to the page, such as PDFs, sortable, each with a description per language and tags. Its settings are `max_files`, `extensions` (default `pdf doc docx xls xlsx ppt pptx txt csv zip`), `max_kb` and `tags`, as for images.

## From modules and apps

### `icon`

An icon from the admin's [Lucide](https://lucide.dev/icons) set, such as `calendar`, chosen with a picker that previews each icon. Comes with `lynxcms/admin`. Templates read the icon's name:

```blade
<x-dynamic-component :component="'lucide-'.$page->badge" class="size-5" />
```

### `component`

A Livewire component of your app that editors place on a page, with settings they fill in. Register the components editors may choose in a service provider:

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Facades\Lynx;

Lynx::components()->register('enrol-form', EnrolForm::class,
    description: 'Enrolment form',
    settings: fn (JsonSchema $schema) => ['capacity' => $schema->integer()],
    rules: ['capacity' => ['nullable', 'integer', 'min:1']],
);
```

The field's `allowed` setting limits which components editors may pick. In the page's view, render the component chosen in a `component` field, here one named `enrol`, with `@lynxComponent($page, 'enrol')`. Settings are shared by every language, so keep them to configuration such as a capacity or a layout. They also reach the visitor's browser with the component's state, so never put secrets in them, such as an API key or an address you don't publish; keep those in config. Components that use the `LynxCms\Core\Livewire\UsesLynxPage` trait read the page with `$this->page()` and keep the visitor's language across Livewire requests.

### `options`

A list of choices that visitors pick from, such as the answers to a form question. The list itself is the page's value, unlike a [`choice`](#choice) field, where an editor picks from a list in the field's settings. Comes with `lynxcms/forms`, whose form builder creates these fields, so the field form doesn't offer the type. See [Forms](/modules/forms).

### `password`

A password visitors type to open a page, stored encrypted; empty means none. Comes with `lynxcms/page-password`. See [Page Passwords](/modules/page-passwords).
