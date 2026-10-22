---
title: Fields and templates
description: Field types, translatable fields, conditions, formatters and template rules.
order: 2
---

# Fields and templates

## Fields

```php
lynx()->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
lynx()->fields->add('price', 'integer');
lynx()->fields->add('related', 'page');
```

Each field gets its own table (`lynx_f_{name}`), created at runtime. Field names are lowercase letters, digits and underscores, starting with a letter.

| Type | Stores |
| --- | --- |
| `text` | A single line of text. |
| `textarea` | Longer text; `format: html` adds the rich text editor and cleans the HTML on save. |
| `integer` | A whole number. |
| `decimal` | A number with decimals, such as a price or a rating, rounded to `places` (default 2). Templates read a float. A comma also works as the decimal point in the admin. |
| `choice` | One of a fixed list of options, or several with `multiple: true`. Each option has a value and a label per language; editors pick with a select, radios or checkboxes (`input`). See below. |
| `email` | An email address, checked on save, with the domain stored in lower case. Templates read a string. |
| `url` | A web address starting with `http://` or `https://`, so a link never runs script. `relative: true` also accepts a path on the site, such as `/contact/`. Can be translatable. |
| `date` | A date, or a date and time with `time: true`. Templates get a `CarbonImmutable`: `$page->starts_on->isoFormat('D MMMM YYYY')`. |
| `time` | A time of day without a date, such as an opening hour, stored as `HH:MM` (or `HH:MM:SS` with `seconds: true`). Templates read the same string. Editors may type `9h30` or `9.30`. Selectors compare padded times: `opens<12:00`. |
| `toggle` | On or off. |
| `icon` | An icon from the admin's [Lucide](https://lucide.dev/icons) set, such as `calendar`, chosen with a picker that previews each icon. Templates read the name: `<x-dynamic-component :component="'lucide-'.$page->badge" />`. Comes with `lynxcms/admin`. |
| `page` | References to other pages, read as a `PageArray`. `multiple: false` allows one, read as that page or `null`; `templates` limits the choice. Editors search by title, or with `input: select`, `radios` or `checkboxes` pick from a list of the allowed pages (up to 100), which suits short lists such as categories. |
| `page_table` | Pages made for this page and listed in a table, like a course's sessions or an event's speakers. Settings: `templates` (required), `parent` (the path new items go under; default the page itself), `columns` (fields the admin table shows; default the title) and `remove` (`keep`, the default, or `trash`). In the admin, editors add an item by its title, then edit it as a page. New items are hidden from navigation. Templates read a `PageArray`; selectors compare ids like page references. |
| `component` | An app Livewire component editors choose, with its settings. |

`lynx()->fields->types()` lists the types with their settings.

**Translatable fields** store one value per language. Other fields are shared by every language.

**Formatters** change how text is shown, never what is stored: `settings: ['formatters' => ['markdown', 'smartypants']]` runs them in order when a page is shown. `$page->getUnformatted('body')` returns the stored value. `heading_anchors` gives headings ids made from their text, so links can point at a section.

**Links in rich text** open a dialog with two tabs. On Link, editors type an address, an email (`mailto:`), an `#anchor`, or words that find a page by title; Select page browses the page tree, Select section lists the headings of the chosen page (or of the page being edited, for an `#anchor` on the same page), and Select file lists the files and images of the page being edited and of the page chosen. Headings become sections when their field uses the `heading_anchors` formatter, which gives each h2, h3 and h4 an id made from its text (`Opening hours` becomes `#opening-hours`). A chosen page links to its address in the language being edited. On Attributes, editors set a title, where the link opens and its relations (`nofollow`, `sponsored`, `noreferrer`). The choices come from `lynx-admin.links` in the admin config; add `ugc` there for sites whose visitors write content. Saving keeps only those four relations, adds `noopener` to links that open a new window, and refuses addresses that could run script. Links do not get `noreferrer` unless an editor asks for it, so sites you link to can see the visits you send them. Modules can offer more files with `app(\LynxCms\Admin\Editor\LinkFiles::class)->add(...)`.

## Labels in every language

Labels, descriptions and notes (a short hint under the input) can be one text or one per language:

```php
lynx()->fields->add('role', 'text', translatable: true,
    label: ['pt' => 'Função', 'en' => 'Role'],
    settings: ['notes' => ['pt' => 'Por exemplo, Realização', 'en' => 'For example, Direction']]);
```

Editors read them in their language, falling back like content does. Templates take the same shape for their own label and for the labels they give their fields.

## Templates

```php
lynx()->templates->add('course', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'price']);
lynx()->templates->update('course', ['child_templates' => ['lesson'], 'allow_children' => true]);
```

| Rule | Meaning |
| --- | --- |
| `has_url` | `false` for pages that are content or settings, never shown at a URL. |
| `singleton` | Only one page may use the template (settings pages). |
| `allow_children` | Whether its pages may have children. |
| `child_templates` | Templates its children may use; empty means any. |
| `parent_templates` | Templates its parent may use; empty means any. |
| `allowed_templates` | Templates its pages may switch to. |

Saving, moving and switching templates refuse placements these rules forbid.

## Layout

A template can arrange its fields in the page editor: extra tabs next to Content, sections that group fields, and a sidebar column for fields editors rarely change.

```php
lynx()->templates->update('project', [
    'tabs' => [['key' => 'credits', 'label' => ['pt' => 'Créditos', 'en' => 'Credits']]],
    'fields' => [
        ['name' => 'title', 'required' => true],
        'summary',
        ['section' => 'info', 'label' => 'Details', 'column' => 'sidebar', 'fields' => ['category', 'year', 'client']],
        ['section' => 'media', 'label' => 'Image', 'collapsed' => true, 'fields' => ['hero']],
        'blocks',
        ['name' => 'credits', 'tab' => 'credits'],
    ],
]);
```

| Setting | On | Meaning |
| --- | --- | --- |
| `tabs` | template | Tabs besides Content, each with a `key` and a `label`. They come after Content and before Texts and Settings. |
| `section` | field list | A titled group of fields. Its fields move together and take its tab and column. Sections do not nest. |
| `collapsed` | section | The section starts closed. Editors can open it, and the editor remembers their choice per template in their browser. A section with an error always opens. |
| `tab` | field or section | The tab it appears in; `content` when not set. |
| `column` | field or section | `main` (default) or `sidebar`. |
| `editor` | field | How the field shows in the page editor: `open` (default), `collapsed`, `collapsed_blank` (closed while empty), `collapsed_populated` (closed while filled), `locked` (the value shows, editors cannot change it) or `hidden` (not shown). Locked and hidden fields are written only by code, imports, actions, agents and MCP, and cannot be required. |
| `width` | field | `25`, `33`, `50`, `66`, `75` or `100` (default): fields in a card sit side by side when it is wide enough. |
| `placeholder` | field | Text in the empty input, per language like `label`. |
| `icon` | field | A [Lucide](https://lucide.dev/icons) icon name shown before the label, such as `calendar`. |

In the admin, **Settings > Templates** edits the layout. Each field's Overrides button sets its label, description, notes, conditions, required, searchable, how it shows in the editor, width, placeholder and icon. Each tab shows a Main and a Sidebar list, and fields and sections are dragged between tabs, columns and sections. A section moves with its fields. The Move to menu on each row does the same without a mouse. Removing a section keeps its fields where it was; removing a tab moves its fields to Content. Nothing is stored until Save fields.

A section shows how many fields it holds, and a dot when one of them falls back to another language. Saving refused for a field on another tab opens that tab. The field order stays one list, so values, selectors and `templates->fields()` are not affected, and a template without these settings looks as before. Schema files write sections inside the field list, in the same shape.

## Choices

```php
lynx()->fields->add('level', 'choice', settings: [
    'input' => 'radios',
    'options' => [
        ['value' => 'beginner', 'label' => ['en' => 'Beginner', 'pt' => 'Iniciante']],
        ['label' => ['en' => 'Advanced', 'pt' => 'Avançado']],   // value "advanced", made from the label
    ],
]);
```

```blade
{{ $page->level }}                          {{-- the label in the current language --}}
@if ($page->level?->value === 'advanced') … @endif
@foreach ($page->formats as $format) {{ $format->label() }} @endforeach   {{-- with multiple: true --}}
```

Pages store the value, so labels can change and be translated freely; changing a value leaves pages that had it without a choice. A single choice reads as a `Choice` or `null`, several as a `ChoiceList` (`has('online')`, `values()`, `labels()`). Selectors compare values: `level=advanced`, `formats=online|hybrid`. In the admin, **Settings > Fields** edits the options with the same list editor as form options.

## Repeaters

A repeater holds a list of items that each have the same fields, such as credits with a partner and a role. Items are sortable and can be switched off.

```php
lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
lynx()->fields->add('role', 'text', translatable: true);
lynx()->fields->add('credits', 'repeater', settings: [
    'fields' => [['name' => 'partner', 'required' => true], ['name' => 'role']],
    'max' => 10,
    'item_label' => '{partner} · {role}',
]);

$page->credits = [
    ['values' => ['partner' => [7], 'role' => ['pt' => 'Realização', 'en' => 'Direction']]],
    ['enabled' => false, 'values' => ['partner' => [9], 'role' => 'Editing']],
];
$page->save();
```

```blade
@foreach ($page->credits as $credit)  {{-- items switched on, in order --}}
    {{ $credit->partner?->title }}: @field($credit->role)
@endforeach
```

Each item is a row of the repeater's table, plus one row per language that has translations, so a missing translation falls back per subfield. Every item keeps a `key`; pass it back when saving to update an item, and leave it out for new ones.

## Matrix

A matrix holds typed blocks: each item has a type and that type's fields.

```php
lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
    ['slug' => 'text',  'label' => ['pt' => 'Texto', 'en' => 'Text'],  'fields' => [['name' => 'body']]],
    ['slug' => 'video', 'label' => ['pt' => 'Vídeo', 'en' => 'Video'], 'fields' => [['name' => 'video_url', 'required' => true], ['name' => 'caption']]],
]]);

$page->blocks = [
    ['type' => 'text', 'values' => ['body' => ['pt' => '<p>Olá</p>', 'en' => '<p>Hello</p>']]],
    ['type' => 'video', 'values' => ['video_url' => 'https://vimeo.com/123']],
];
```

Slugs never change, so reordering types or editing their labels never changes what a block is. A block that changes type keeps its other values, hidden. A template can offer only some types: `['name' => 'blocks', 'allowed_types' => ['text']]`. See [Templates and views](/docs/front-end/templates) for rendering.

## Conditions

A template field can show or require itself only when other fields match, written like ProcessWire's `showIf`:

```php
['name' => 'venue', 'required' => true, 'show_if' => 'format=onsite|hybrid']
['name' => 'summary', 'required_if' => 'format=online, price>0']
```

Clauses separated by commas must all match, `|` separates alternatives, and an empty value means empty. Operators: `=`, `!=`, `>`, `<`, `>=`, `<=` and `%=` (contains). A hidden field is never required. Required fields are checked in the default language only.

## Search

The search index holds, for each page and language, the words of its title and of the fields its template marks searchable. In the admin, tick "Searchable" on the field in the template's screen; in code, set it on the template field:

```php
lynx()->templates->update('project', ['add' => [['name' => 'blocks', 'searchable' => true]]]);
```

A searchable repeater or matrix adds the text of its enabled items. Text, textarea, email and url fields give their words (markup removed) and choices their labels in each language. Numbers, dates, toggles and page references add nothing. The admin's search box and `search*=` selectors use the index. The page pickers (page fields, the link dialog, the menu editor) list title matches first, then pages found through the index.

Saving, restoring, moving to another template or deleting a page updates it, and so does changing which fields a template marks. After changing a field's own settings, such as a choice label or a matrix type's fields, run `php artisan lynx:search-index`. Drafts are indexed when they are published.

## Removing fields

Removing a field from a template, or switching a page to another template, keeps the values as orphans. `pages.purge_orphans` deletes them when you are sure. Deleting a field deletes its table and values.

## System templates

Modules own some templates and fields (the trash, menus). The admin hides them unless you tick **Show system templates** and shows them read only.
