---
title: Custom Field Types
description: Write your own field type, with storage, validation, selectors, settings and an admin input.
---

# Custom Field Types

Lynx's [field types](/basics/field-types) cover most content, but a site can need its own: a star rating, a colour from a brand palette, a product code checked against an external system. A field type decides how a value is stored, validated, compared in selectors and shown in templates, and every part of Lynx then works with it: saving through any action, selectors, schema files, content packages, the MCP server and the AI agent.

## A first field type: star ratings

A rating from 1 to 5 stars, or up to a maximum the field sets, is a small, complete example. Extend `BaseFieldType` and answer only what differs from the defaults:

<<< @/snippets/advanced/RatingType.php#type{php}

Register the type in a service provider. With the admin installed, also tell the page editor which input to show; a rating is a whole number, so the built-in integer input and its converter do:

<<< @/snippets/advanced/rating-register.php#register{php}

The type now works like a built-in one:

```php
lynx()->fields->add('rating', 'rating', settings: ['max' => 10]);
lynx()->templates->update('post', ['add' => ['rating']]);

$post->rating = 8;  // validated on save: 11 is refused
$post->save();

lynx()->pages->find('template=post, rating>=7, sort=-rating');
```

```blade
@if ($post->rating)
    <p>{{ str_repeat('★', $post->rating) }}</p>
@endif
```

## What a field type can answer

| Method | Does | Default |
| --- | --- | --- |
| `name()`, `description()` | The type's name, and one sentence the field form, MCP tools and the agent read. | Required |
| `columns()` | Adds the value column(s) to the field's table. | Required |
| `comparison()` | How selectors compare values: `Comparison::Text`, `Integer`, `Decimal`, `Boolean`, `Date` or `None`. | `Text` |
| `settingsSchema()`, `settingsRules()` | The field's settings, described for the admin and AI tools, and validated. | None |
| `rules()`, `normalize()` | Validate a value on every save, and clean it before it is stored. | None, unchanged |
| `sleep()`, `wake()` | Turn a value into rows of the table and back, for values that span several rows or columns. | One value in `data` |
| `supportsTranslation()` | Whether fields of the type can be translatable. | `false` |

## Optional interfaces

- `PresentsValue`: return what templates read, such as a value object, instead of the stored value. `getUnformatted()` still returns the stored value.
- `ReferencesPages`: list the page ids a value points at (eager loading), map them to page keys and back (content packages), and narrow a query to rows that reference a page (backlinks).
- `DescribesPhpType`: the PHPDoc type templates read for a shared value, such as `'\Carbon\CarbonImmutable|null'`, for `lynx:phpstan`. Without it the field reads as `mixed`.

## Static analysis

Fields are read as magic properties (`$page->starts_on`, `$item->role`), which PHPStan cannot see. Lynx ships a PHPStan extension that types them from your schema. Write the types, then include the extension in `phpstan.neon`:

```bash
php artisan lynx:phpstan
```

```neon
includes:
    - vendor/lynxcms/core/phpstan/extension.neon
```

Each field then reads as its type presents it, on pages and on repeater and matrix items: a `TranslatableValue` for translatable fields, `int|null` for an integer, a `PageArray` for page references (`Page|null` with `multiple: false`), a single image or `null` for an images field with `max_files: 1`. Writes accept any value, as `pages.save` validates them; of the built-in properties, only `$page->name` may be set. Every field is listed, so reading a field the page's template does not have still returns `null` at runtime.

`lynx:phpstan` writes `lynx/phpstan/types.php`. Commit it, and regenerate it after changing fields, for example in the same commit as `lynx:schema:export`. Elsewhere, point the extension at it with `parameters.lynx.types`. PHPStan 2.3 and later analyse the code that reads fields again when the file changes; with an older PHPStan, run `vendor/bin/phpstan clear-result-cache` after regenerating it, or the result cache keeps reporting the old fields.

## In the admin

```php
app(\LynxCms\Admin\Editor\FieldInputs::class)->register(
    'rating',
    'my-module::inputs.rating',   // the input view
    RatingConverter::class,       // a FormConverter: stored value to form value and back
    'my-module::values.rating',   // optional read-only display view
);
```

A converter that also implements `PreparesDisplay` prepares stored values for the display view, for example loading page titles in one query.

## A complete example: the icon field

The `icon` field type that comes with the admin is a small, complete field type, built the way your own would be. It stores an icon name such as `calendar`, checks it against a list of names (its custom data), and lets editors choose with a picker that previews each icon. Its files are in `packages/admin`.

### 1. The data source

Keep the custom data in a class of its own, so the field type, the admin input and your templates all ask the same thing. `LynxCms\Admin\Icons\IconSet` reads the names from the Lucide icon set once per request and answers three questions:

```php
$icons = app(IconSet::class);
$icons->names();                // every name, sorted
$icons->has('calendar');        // true
$icons->search('map pin', 60);  // names containing every word, those starting with "map" first
```

The data could as well come from a config file, a database table or an API; register the class as a singleton so it is loaded once.

### 2. The field type

`LynxCms\Admin\Fields\IconType` extends `BaseFieldType`. Each method answers one question:

| Method | In the icon field |
| --- | --- |
| `name()` | `'icon'`: the type name used in `fields.create`, schema files and here. |
| `description()` | One sentence for agents and MCP tools, which read it to choose a type. |
| `columns()` | One nullable `string('data', 64)` column. |
| `comparison()` | Left to `BaseFieldType`, which compares as text, so selectors filter by icon: `badge=calendar`. |
| `rules()` | Refuses a name the data source does not have, with a message editors understand. |
| `normalize()` | Trims the name; an empty one is stored as `null`. |
| `phpType()` (`DescribesPhpType`) | A nullable string, for `lynx:phpstan`. |

`sleep()` and `wake()` are also left to `BaseFieldType`, which stores one value in `data`. Override them when a value spans several rows or columns.

### 3. Registering the type

In a service provider's `boot()`:

```php
$this->app->singleton(IconSet::class);
$this->app->make(FieldTypeRegistry::class)->register(IconType::class);
```

From then on `lynx()->fields->add('badge', 'icon')` works, and so do `pages.save`, selectors, schema sync and content packages.

### 4. The admin input

The input is a Livewire component, `LynxCms\Admin\Livewire\IconPicker`, because it searches the data source as the editor types. Its `$value` property has `#[Modelable]`, so the page editor binds it like any input. It accepts only names `IconSet` has, and shows at most 60 icons at a time. The input view only places it:

```blade
{{-- inputs/icon.blade.php --}}
<livewire:lynx-admin.icon-picker wire:model="{{ $model }}" :disabled="$disabled" wire:key="icon-{{ $model }}" />
```

Input views receive `$model` (the form property to bind), `$disabled`, `$field` (the `FieldView`), `$code` (the language), `$value` and `$placeholder`. A plain input does not need Livewire: `inputs/url.blade.php` reuses the text input.

### 5. The read-only display

Locked fields, and editors without edit access, see the value through a display view. `values/icon.blade.php` shows the icon and its name, and only the name when the set no longer has that icon, so a removed icon never breaks the page editor.

### 6. Registering the input

```php
app(FieldInputs::class)->register('icon', 'lynx-admin::inputs.icon', TextConverter::class, 'lynx-admin::values.icon');
```

The converter turns stored values into form values and back. A name is the same in both, so `TextConverter` does. A type whose form value differs, such as a list of page ids shown as titles, writes its own `FormConverter`.

### 7. In templates

The value is the name, so the template chooses how to draw it:

```blade
@if ($page->badge)
    <x-dynamic-component :component="'lucide-'.$page->badge" class="size-6" />
@endif
```

### 8. Tests

`tests/Feature/Admin/IconFieldTest.php` covers each part: the data source lists and searches, the type stores a valid name and refuses an unknown one, the picker searches with previews and refuses names it does not have, and the page editor and the Templates screen use the picker.

The same picker sets the icon a template shows beside a field's label (the `icon` override in Settings > Templates), so one data source serves both.
