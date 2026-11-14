---
title: Templates and Fields
description: Create fields and templates, set where pages may go, arrange the editor and change the schema safely.
---

# Templates and Fields

Templates and fields decide what each kind of page holds. A course has a title, a summary, a price and related courses, and editors get a form with exactly those inputs, in plain labels, in every language the site has.

Every page uses a **template**, and a template is a list of **fields**. Together they are the site's *schema*: what each kind of page holds and how editors fill it in. If you have used Laravel's models, a template plays the part of a model's database table, and a field the part of a column, except that you change them while the site runs and Lynx creates the tables for you.

You can build the schema in the admin, under **Settings › Fields** and **Settings › Templates**, or in PHP. Both run the same actions, so they validate the same way and show up in the same activity log. The examples on this page use PHP; every setting has a matching control in the admin.

## Creating fields

Create a field with `lynx()->fields->add()`, giving its name and [type](/basics/field-types):

```php
lynx()->fields->add('summary', 'textarea', translatable: true);
lynx()->fields->add('price', 'decimal', settings: ['places' => 2]);
lynx()->fields->add('related', 'page', settings: ['templates' => ['course']]);
```

Field names use lowercase letters, digits and underscores, and start with a letter: they become property names in your templates (`$page->related`). Each field gets its own table, `lynx_f_{name}`, created at once.

Some names are taken, because pages or selectors already use them. Lynx refuses them with a message saying so, so you find out when you create the field, not when a selector misbehaves: `id`, `name`, `names`, `template`, `parent`, `parents`, `children`, `status`, `sort`, `path`, `url`, `created`, `modified`, `published`, `created_at`, `updated_at`, `published_at`, `created_by`, `modified_by`, `language`, `languages`, `page`, `pages`, `settings`, `has_parent`, `has_draft`, `has_schedule`, `has_outdated`, `limit`, `start`, `include`, `join`, `lang` and `search`. Name the field after what it holds instead, such as `availability` for a session's state.

A field is created once and used by every template that lists it. The `title` field that `lynx:install` creates is used by most templates, for example.

### Translatable fields

A **translatable** field holds one value per language, such as a title or a body text. Any other field is **shared**: its one value is the same in every language, which share it, such as a price or a date. Only text, textarea and URL fields can be translatable. [Field Types](/basics/field-types#shared-and-translatable) explains the difference, and [Languages](/basics/languages) how values fall back between languages.

A shared field can be made translatable later, as [Making a field translatable](#making-a-field-translatable) shows, but a translatable field can't be made shared again. On a site with one language the admin doesn't ask, and makes fields translatable whenever their type allows, so a language added later can be translated without changing any field.

### Labels, descriptions and notes

A field's label, description and notes (a short hint under the input) are what editors read in the admin. Each can be one text, or one text per language:

```php
lynx()->fields->add('role', 'text', translatable: true,
    label: ['en' => 'Role', 'pt' => 'Função'],
    settings: ['notes' => ['en' => 'For example, Direction', 'pt' => 'Por exemplo, Realização']]);
```

The keys are language codes (see [Languages](/basics/languages#adding-a-language) for giving the default language a code such as `en`). Editors read each text in their own language, falling back like content does.

### Duplicating a field

To make a field like one you have, such as a second date with the same format, duplicate it. On the **Fields** screen, the copy button beside a field asks for the new name, and the new field opens on its own screen. In code:

```php
lynx()->fields->duplicate('starts_on', 'ends_on', label: 'Ends on');
```

The new field gets the same type, labels (unless you give one), translation and settings. It is a field of its own: no template uses it until you add it, and no values are copied.

### Making a field translatable

A shared text, textarea or URL field can be made translatable, for example when a site gets its second language and a field made shared turns out to need translating. On the field's screen, **Make translatable** does it after asking. In code:

```php
lynx()->fields->makeTranslatable('venue');
```

Every value becomes the default language's value, on live pages, their drafts, and in the items of repeaters and matrices that show the field. Other languages start empty and [fall back](/basics/languages#fallback) to it until someone translates them, so visitors see no change. Revisions and content packages saved before read the old value as the default language's.

It only goes one way: a translatable field can't be made shared again, because that would lose every translation. To get there, create a shared field, copy the values you want, and delete the old one.

If the field is listed in `lynx.sort_keys`, run `php artisan lynx:sort-keys` afterwards so sorting by it uses sort keys again.

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

A page without a URL shows wherever a template puts it, in every language, so its page editor's **Settings** tab asks for one name, the one templates and code find it by, instead of a name and a published switch per language. Its schedule publishes or unpublishes the whole page, and it has no **Hidden** status or old URLs, which only matter for pages with an address.

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
| `allowed_templates` | Templates its pages may switch to. |

In the admin, a template's **Family** section sets the same rules: search for a template by its label or name to add it to a list, remove one with its ×, and leave a list empty to allow any template.

Modules keep their own content in hidden system branches, such as menus under `/_menus/`. Those pages have the `system` status and are not bound by your family rules, so you can limit the home page's children to your own templates and still install modules.

## Arranging the page editor

By default the page editor shows a template's fields in order, on one tab, with a panel beside them: whether the page is published or has a draft, how much of it each language has its own text for, and its latest activity. A template can arrange its fields: extra tabs next to Content, sections that group fields, and a sidebar for fields editors rarely change. A tab with a sidebar shows that sidebar instead of the panel.

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

### Copying a section to other templates

When several templates need the same group of fields, such as an address, build the section once and copy it. In the admin, the copy button on a section opens **Copy section to templates**: tick the templates and choose **Copy**. In code:

```php
lynx()->templates->copySection('office', 'address', to: ['event', 'venue']);
```

Each template gets the section, its fields with their settings on the original template, and its tab when it doesn't have one. The copies are not linked: changing the section on one template later leaves the others as they are.

A template that already has one of the section's fields is refused, since a field appears on a template once. With `renameConflicts: true` (**Rename fields that clash** in the admin), that template gets a new field instead, a copy of the original named with a prefix: `address_city` for `city`. The prefix defaults to the section's key and `_`, and `prefix:` changes it. New fields start empty, and conditions inside the section follow the new names. If a field with the new name already exists, the copy is refused and you choose another prefix.

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

## Drafting the schema

For a new site, or a batch of new templates, it is often quicker to write them down than to click through the admin. A draft is a short YAML file that `php artisan lynx:draft` turns into fields and templates, with a starting view for each template with a URL that it creates or changes and that has none yet, such as the `home` template `lynx:install` made, and for each block type of their matrix fields. Views that exist are kept; `--force-views` replaces only the views of templates the draft creates.

```yaml
fields:
  intro: textarea
  starts_on: date
  ticket_price: decimal:2 min:0
  seats: integer min:1
  sold_out: toggle
  topics: choice:Design,Development,"Product management" multiple

templates:
  events:
    children: [event]
    fields: [title*, intro]
  event:
    parents: [events]
    fields:
      - title*
      - intro
      - starts_on*
      - venue: text:120
      - section: Practical details
        fields: [ticket_price, seats, sold_out, topics]
```

A field is its type, then what you want to change about it. Pick the type that matches the data, since a field's type can't change later: `toggle` for yes or no, `integer` for whole numbers such as a year, `decimal` for prices, `date` and `time`, `choice` for a fixed list, `page` for links to other pages. They store and filter as what they are, so `sold_out=0` and `year>=2020` compare real values. [Field Types](/basics/field-types#available-types) lists them all.

- `decimal:2` fills the type's main setting, here two decimal places. The types that take one are `text` (maximum length), `textarea` (format), `decimal` (places), `choice` (options) and `page` and `page_table` (templates).
- Text, textarea and URL fields get a value per language, as the admin creates them on a site with one language, so a language added later can be translated without changing any field. `translatable:false` makes the field [shared](/basics/field-types#shared-and-translatable), with one value for every language, as for a product code. A field the site already has keeps its choice unless the draft says so: `translatable` makes a shared field translatable, keeping its values as the default language's.
- A word on its own, such as `multiple`, turns a setting on. `key:value` sets any other setting, and a list is written with commas: `templates:course,workshop`.
- `label:"Ticket price (EUR)"` sets the label. Without one, `ticket_price` is labelled "Ticket price".
- Anything the short form can't say, such as a repeater's fields or labels in several languages, goes in the long form: the field as a map of `type`, `label`, `translatable` (`false` for one value in every language) and its settings.

```yaml
fields:
  speaker_name: text
  speakers:
    type: repeater
    label: { en: Speakers, pt: Oradores }
    fields: [speaker_name*, intro]
    max: 6
```

A choice field's options can be translated in the long form: each option is its label in the default language, followed by its other languages. The stored value is the label made URL safe (`Beginner` is `beginner`), as in the short form.

```yaml
fields:
  level:
    type: choice
    options:
      Beginner: { pt: Iniciante }
      Advanced: { pt: Avançado }
```

An option the site has keeps its translations when the draft gives it in one language only, as in `choice:Beginner,Advanced`, so options translated in the admin survive building the draft again. Giving a translation in the draft replaces the site's.

In a template, `title*` makes a field required there, and `venue: text:120` defines a field in place. `url: false` makes a template for settings or reusable content, `singleton: true` allows one page, and `children`, `parents` and `allowed` set where pages may go. Sections and tabs work as in [Arranging the page editor](#arranging-the-page-editor).

```bash
php artisan lynx:draft --stub        # writes lynx/draft.yaml to start from
php artisan lynx:draft --dry-run     # lists what would change, and changes nothing
php artisan lynx:draft               # builds it, after asking in a terminal
```

A build runs in one transaction, through the same actions as the admin, so it all happens or none of it does. Every mistake is reported before anything changes, with the closest name when you mistype one (`decmal` suggests `decimal`). A draft adds and changes fields and templates; it never deletes them. For a field or template the site already has, a label the draft leaves out stays as it is, and so do settings it doesn't name, so building the same draft twice changes nothing. A draft can't change a field's type or make a translatable field shared, and it can't touch what Lynx or a module owns, such as menus.

For a template the site already has, the draft's `fields` list becomes the template's list, in the draft's order. A draft that leaves out a field the template has is refused, and the error names the fields. List them to keep them, or build with `--prune-fields` to take them off the template; the plan then shows `remove field body from template course` for each. The fields and their values stay in the site.

A field the draft only names keeps the settings it has on that template, such as its label, description, tab, width or whether it is required. So adding a field to a template means listing its current fields by name, plus the new one, where you want it:

```yaml
templates:
  home:
    fields: [title*, sections, hero_text, seo_title, seo_description]
```

Settings the draft gives win, and `null` clears one: `{ name: seo_title, description: null }`. Where a field sits is the draft's to say, so a field the draft lists outside its old section leaves that section. `--from-site` writes each template's full list, which is the quickest place to copy it from.

After building, `lynx:draft` also keeps the files the site already has up to date: the draft's fields and templates in `lynx/schema`, and the types `lynx:phpstan` and `lynx:ide-helper` wrote. It starts none of them, so a site without a `lynx/schema` folder is told to run `lynx:schema:export`. The draft itself is a starting point: `lynx/schema` stays the record, and you can delete the draft once it is built.

A draft can also create pages, so a new site has its tree to fill in. List them by URL path under `pages`:

```yaml
pages:
  /events: Events                    # a title; the template is the one named like the last segment
  /about: { template: page, title: About }
  /events/launch:
    template: event
    title: Launch party
    starts_on: 2026-12-01            # required fields need a value
```

Pages are created unpublished, after the fields and templates, with the title and the values you give; every other field stays empty. The template defaults to the one named like the path's last segment, and the title to the segment itself (`/about` is titled "About"); a template without a `title` field, such as site settings, gets none. A parent must exist or come earlier in the list. Everything a save would refuse is reported before anything changes: a required field without a value (files can wait until the page is saved), a field the template lacks, a place the family rules refuse, or a second page on a singleton template. A path the site already has is left as it is, so building the draft again creates nothing. `--from-site` doesn't write pages, and the `schema.draft` tool refuses a draft that has them.

Two more options help with an existing site:

- `php artisan lynx:draft --from-site` writes the site's own fields and templates as a draft, in the short form wherever it fits. It is a quick way to read a site's schema, and building it changes nothing.
- `php artisan lynx:draft --json-schema` writes `lynx/draft.schema.json`, next to the draft. Editors with a YAML language server (the YAML extension in VS Code, or PhpStorm) then check drafts in that folder as you type, underlining unknown types and keys and suggesting types, settings and template keys, when the draft's first line is `# yaml-language-server: $schema=./draft.schema.json`. A draft written by `--stub` or `--from-site` already starts with it. The short form is one string to the editor, so only its type is checked there; `--dry-run` checks the rest. Run `--json-schema` again after installing a module, for its field types.

An AI agent connected through MCP can build a draft too, with the `schema.draft` tool. It shows you what would change first, needs your agreement (`prune_fields`) to take fields off a template, and it only changes the database: the views, `lynx/schema` and the type files stay yours to update. Where schema editing is off ([On production](/deeper/schema-and-content#on-production)), the tool is refused, as every schema change by an agent is.

## Keeping the schema in Git

Fields and templates live in the database, but you can keep them in your repository, like migrations. `php artisan lynx:schema:export` writes them as YAML files, and `php artisan lynx:schema:apply` applies the files on another machine. [Schema and Content Packages](/deeper/schema-and-content) covers both, and how to make the admin's schema screens read only in production.

## System templates

Modules own some templates and fields, such as the trash and menus. The admin hides them unless you tick **Show system templates**, and shows them read only.
