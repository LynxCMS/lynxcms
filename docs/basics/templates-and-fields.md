---
title: Templates and Fields
description: Create fields and templates, set where pages may go, arrange the editor and change the schema safely.
---

# Templates and Fields

Every page uses a **template**, and a template is a list of **fields**. Together they are the site's *schema*: what each kind of page holds and how editors fill it in. If you know Eloquent, a template plays the part of a model's table, and a field the part of a column, except that you change them while the site runs and Lynx creates the tables for you.

You can build the schema in the admin, under **Settings › Fields** and **Settings › Templates**, or in PHP. Both run the same actions, so they validate the same way and show up in the same activity log. The examples on this page use PHP; every setting has a matching control in the admin.

## Creating fields

Create a field with `lynx()->fields->add()`, giving its name and [type](/basics/field-types):

```php
lynx()->fields->add('summary', 'textarea', translatable: true);
lynx()->fields->add('price', 'decimal', settings: ['places' => 2]);
lynx()->fields->add('related', 'page', settings: ['templates' => ['course']]);
```

Field names use lowercase letters, digits and underscores, and start with a letter: they become property names in your templates (`$page->related`). Each field gets its own table, `lynx_f_{name}`, created at once.

Some names are taken, because pages or selectors already use them, and are refused with a message saying so: `id`, `name`, `names`, `template`, `parent`, `parents`, `children`, `status`, `sort`, `path`, `url`, `created`, `modified`, `published`, `created_at`, `updated_at`, `published_at`, `created_by`, `modified_by`, `language`, `languages`, `page`, `pages`, `settings`, `has_parent`, `limit`, `start`, `include`, `join`, `lang` and `search`. Name the field after what it holds instead, such as `availability` for a session's state.

A field is created once and shared by every template that uses it. The `title` field that `lynx:install` creates is used by most templates, for example.

### Translatable fields

A **translatable** field holds one value per language, such as a title or a body text. Any other field is **shared**: one value for every language, such as a price or a date. Only text, textarea and URL fields can be translatable. [Field Types](/basics/field-types#shared-and-translatable) explains the difference, and [Languages](/basics/languages) how values fall back between languages.

Whether a field is translatable can't be changed after it is created, so choose with care. On a site with one language the admin doesn't ask, and makes fields translatable whenever their type allows, so adding a language later just works.

### Labels, descriptions and notes

A field's label, description and notes (a short hint under the input) are what editors read in the admin. Each can be one text, or one text per language:

```php
lynx()->fields->add('role', 'text', translatable: true,
    label: ['en' => 'Role', 'pt' => 'Função'],
    settings: ['notes' => ['en' => 'For example, Direction', 'pt' => 'Por exemplo, Realização']]);
```

The keys are language codes (see [Languages](/basics/languages#adding-a-language) for giving the default language a code such as `en`). Editors read each text in their own language, falling back like content does.

## Creating templates

Create a template from a list of fields. A field in the list can be a name, or an array that adds options for this template only, such as `required`:

```php
lynx()->templates->add('course', label: 'Course', fields: [
    ['name' => 'title', 'required' => true],
    'summary',
    'price',
    'related',
]);
```

To add or remove fields later:

```php
lynx()->templates->update('course', ['add' => ['body'], 'remove' => ['related']]);
```

Removing a field from a template keeps its values, as [Changing the schema](#changing-the-schema) explains.

A template's options for a field override the field's own: a template can give a field another label, description, notes or placeholder, make it required, or [show it only when other fields match](#showing-fields-only-when-needed). In the admin, each field of a template has an **Overrides** button for these.

Required fields are checked in the default language only, so a page can be saved before it is translated.

### Pages without a URL, and one-page templates

Two options change what a template is for:

- `has_url: false` makes a template for content that is never shown at its own address, such as a settings page or a list of partners other pages reference.
- `singleton: true` allows only one page on the template, such as the site's settings or a blog's index page.

```php
lynx()->templates->add('settings', fields: ['footer_text'], hasUrl: false, singleton: true);
```

## Where pages may go

Templates can say where their pages belong in the tree. These **family rules** keep the tree tidy: the admin only offers templates that fit, and saving, moving or switching a page refuses a place the rules forbid.

```php
lynx()->templates->update('blog', ['allow_children' => true, 'child_templates' => ['post']]);
lynx()->templates->update('post', ['allow_children' => false, 'parent_templates' => ['blog']]);
```

| Rule | Meaning |
| --- | --- |
| `allow_children` | Whether its pages may have children. |
| `child_templates` | Templates its children may use; empty means any. |
| `parent_templates` | Templates its parent may use; empty means any. |

Modules keep their own content in hidden system branches, such as menus under `/_menus/`. Those pages have the `system` status and are not bound by your family rules, so you can limit the home page's children to your own templates and still install modules.
| `allowed_templates` | Templates its pages may switch to. |

## Arranging the page editor

By default the page editor shows a template's fields in order, on one tab. A template can arrange them: extra tabs next to Content, sections that group fields, and a sidebar for fields editors rarely change.

```php
lynx()->templates->update('project', [
    'tabs' => [['key' => 'credits', 'label' => ['en' => 'Credits', 'pt' => 'Créditos']]],
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
| `tabs` | template | Tabs besides Content, each with a `key` and a `label`. They come after Content and before the editor's own tabs, such as Settings. |
| `section` | field list | A titled group of fields, which move together and share its tab and column. Sections do not nest. |
| `collapsed` | section | The section starts closed. The editor remembers each editor's choice per template, and a section with an error always opens. |
| `tab` | field or section | The tab it appears in; `content` when not set. |
| `column` | field or section | `main` (default) or `sidebar`. |
| `editor` | field | How the field shows: `open` (default), `collapsed`, `collapsed_blank` (closed while empty), `collapsed_populated` (closed while filled), `locked` (shown, not editable) or `hidden`. Locked and hidden fields are written only by code, imports, actions and agents, and can't be required. |
| `width` | field | `25`, `33`, `50`, `66`, `75` or `100` (default): fields sit side by side when there is room. |
| `placeholder` | field | Text in the empty input, per language like a label. |
| `icon` | field | A [Lucide](https://lucide.dev/icons) icon shown before the label, such as `calendar`. |

In the admin, **Settings › Templates** edits the layout. Each tab shows a Main and a Sidebar list; drag fields and sections between tabs, columns and sections, or use the **Move to** menu on each row from the keyboard. Removing a section keeps its fields; removing a tab moves its fields to Content. Nothing is stored until you choose **Save fields**.

The layout only changes the editor. The template's fields stay one list, so values, selectors and schema files are not affected.

## Showing fields only when needed

A field of a template can show, or be required, only when other fields match. Conditions use the same syntax as [selectors](/basics/selectors):

```php
lynx()->templates->update('course', ['fields' => [
    'title',
    'format',
    ['name' => 'venue', 'required' => true, 'show_if' => 'format=onsite|hybrid'],
    ['name' => 'summary', 'required_if' => 'format=online, price>0'],
]]);
```

Clauses separated by commas must all match, and `|` separates alternative values. An empty value means empty: `video_url=` matches when there is no video. The operators are `=`, `!=`, `>`, `<`, `>=`, `<=` and `%=` (contains). A hidden field is never required.

## Making fields searchable

The search index holds, for each page and language, the words of its title and of the fields its template marks **searchable**. The admin's search box and [`search*=` selectors](/basics/selectors#searching-text) read the index. Tick "Searchable" on the field in the template's screen, or in code:

```php
lynx()->templates->update('project', ['add' => [['name' => 'blocks', 'searchable' => true]]]);
```

Text, textarea, email and URL fields add their words, without markup; choices add their labels in each language. A searchable repeater or matrix adds the text of its items that are switched on. Numbers, dates, toggles and page references add nothing.

Saving, restoring, moving to another template or deleting a page updates the index, and so does changing which fields a template marks. After changing a field's own settings, such as a choice label or a matrix type's fields, rebuild it:

```bash
php artisan lynx:search-index
```

## Changing the schema

Lynx keeps content unless you delete it on purpose:

- **Removing a field from a template** keeps its values as *orphans*, and **switching a page to another template** keeps the values of fields the new template lacks. Adding the field back, or switching back, brings them back. `pages.purge_orphans` deletes orphaned values when you are sure.
- **Deleting a field** deletes its table and every value, in every template.
- **Deleting a template** is possible only when no page uses it, counting unpublished pages and pages in the trash. The template's screen in the admin lists the pages that use it, each linking to its Settings tab, where its template is switched. `templates.delete` refuses with the same list, so a script or an AI agent knows which pages to switch with `pages.switch_template` or delete first.

```php
lynx()->pages->switchTemplate('/courses/old', 'archived-course');
lynx()->pages->purgeOrphans(template: 'course', dryRun: true);
```

## Keeping the schema in Git

Fields and templates live in the database, but you can keep them in your repository, like migrations. `php artisan lynx:schema:export` writes them as YAML files, and `php artisan lynx:schema:apply` applies the files on another machine. [Schema and Content Packages](/deeper/schema-and-content) covers both, and how to make the admin's schema screens read only in production.

## System templates

Modules own some templates and fields, such as the trash and menus. The admin hides them unless you tick **Show system templates**, and shows them read only.
