---
name: lynx-content-model
description: Model content in Lynx with fields, templates and languages, and keep the schema in sync between environments. Use when adding fields or templates, making fields translatable, setting template family rules, or exporting and applying schema or content.
---

# Lynx content model

## Fields and templates

Fields are created once and shared by templates. Lynx creates their tables; never write migrations for them.

```php
lynx()->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
lynx()->fields->add('price', 'integer');
lynx()->fields->add('related', 'page'); // references to other pages

lynx()->templates->add('course', fields: [['name' => 'title', 'required' => true], 'body', 'price', 'related']);
lynx()->templates->update('course', ['child_templates' => ['lesson'], 'allow_children' => true]);
```

- Field types: `lynx()->fields->types()`. Translatable fields store one value per language; others are shared.
- Removing a field from a template keeps its values as orphans; only `pages.purge_orphans` deletes them.
- Family rules (`allow_children`, `child_templates`, `parent_templates`) are enforced on create, move and template switch.
- Repeaters list items with the same fields: `lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner'], ['name' => 'role']]])`. Templates loop `$page->credits` (items switched on); pass each item's `key` back when saving.

## Languages

```php
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
```

Languages are rows, not columns. Never copy values between languages to fake a fallback.

## Schema and content between environments

```bash
php artisan lynx:schema:export          # YAML in lynx/schema: commit it
php artisan lynx:schema:apply --dry-run # on the other machine, then without --dry-run
php artisan lynx:export site.zip        # schema and every page
php artisan lynx:import site.zip --with-schema --dry-run
php artisan lynx:backup                 # content backup, newest 14 kept
```

Content packages key pages by their names (`/courses/alpha/`), so imports update matching pages and create missing ones. They are not database backups.
