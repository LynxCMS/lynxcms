---
title: The Content Model
description: Chapter 2 of the training site guide. Languages, fields and templates for courses, sessions, instructors and studios, demo content, and schema files.
---

# The Content Model

The content model is what the school's staff will fill in: a course has a level, hours, a price and a curriculum; a session has a date, a place and seats. This chapter writes it as a seeder, so it is code you can review, run on a fresh database and commit.

## Two languages

Lynx starts with one language, `default`. Give it its real code, then add Portuguese, which falls back to English for anything not translated yet:

<<< @/snippets/training/database/seeders/AtlasSchemaSeeder.php#languages{php}

## Fields

A field is created once and shared by every template that uses it. Text that visitors read is **translatable**, one value per language; numbers, dates and choices are the same in both languages.

<<< @/snippets/training/database/seeders/AtlasSchemaSeeder.php#fields{php}

A few choices worth explaining:

- **`level` is a choice, not text.** Pages store the value (`beginner`), and visitors read the label in their language (*Iniciante*). Filters compare values, so renaming a label never breaks a link.
- **The home page's sections are repeaters.** Highlights, figures, steps, testimonials and questions are lists the staff edit, sort and switch off in the admin, without a developer.
- **Repeaters reuse fields.** `heading` and `text` appear in several repeaters with different labels (*Question*, *Answer*), so there are fewer tables and one way to read them.
- **The session's state is `availability`.** `status` is taken, because pages already have a status (published, hidden). Lynx refuses [reserved names](/basics/templates-and-fields#creating-fields) and says why.

## Templates

<<< @/snippets/training/database/seeders/AtlasSchemaSeeder.php#templates{php}

**Sessions are child pages of their course**, not rows in a table of your own. That gives each session a URL to book from (chapter 7), a place in the admin's tree right under its course, and selectors like `template=session, starts>=now` for free. A course lists its sessions with `$course->children('template=session')`.

**Family rules** keep the tree tidy: under the catalogue, staff can only add courses; under a course, only sessions. The admin offers only those templates, and the API refuses the rest. Modules' own hidden branches, such as menus, are not bound by these rules.

## Demo content

A second seeder fills the site with six courses, twelve sessions, three instructors, two studios, news and the home page's sections, in both languages. Its data lives in `database/seeders/data/atlas.php`; the seeder turns it into pages:

<<< @/snippets/training/database/seeders/AtlasContentSeeder.php{php}

Run both, and keep the schema in Git:

```bash
php artisan db:seed --class=AtlasSchemaSeeder
php artisan db:seed --class=AtlasContentSeeder
php artisan lynx:schema:export
```

`lynx:schema:export` writes one YAML file per language, field, template and role into `lynx/schema`. Commit them. A teammate's machine or a staging server applies them with `lynx:schema:apply`, and a change made in the admin shows up as a diff in a pull request. [Schema and Content Packages](/deeper/schema-and-content) covers how environments stay in step.

## What you get

- **The client:** forms in the admin that match their courses: a level to pick, a price, a curriculum to sort, sessions under each course. Portuguese tabs on every text field.
- **You:** a model in code and in Git, typed values in templates (`$session->starts` is a date, `$course->level` a choice), and selectors that query it all.
