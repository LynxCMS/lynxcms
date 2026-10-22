---
title: Custom field types
description: Write a field type, present its values, declare references and edit it in the admin.
order: 4
---

# Custom field types

A field type owns its storage, conversion and validation. Extend `LynxCms\Core\Fields\Types\BaseFieldType` and register it in a service provider:

```php
lynx()->fieldTypes()->register(RatingType::class);
```

| Method | Does |
| --- | --- |
| `columns()` | Adds the value column(s) to the field table. |
| `normalize()`, `rules()` | Clean and validate a value on every save. |
| `sleep()`, `wake()` | Convert a value to rows and back. |
| `comparison()` | How selectors compare it: `Comparison::Text`, `Integer`, `Decimal`, `Boolean`, `Date` or `None`. |

## Optional interfaces

- `PresentsValue`: return what templates read, such as a value object, instead of the stored value. `getUnformatted()` still returns the stored value.
- `ReferencesPages`: list the page ids a value points at (eager loading), map them to page keys and back (content packages), and narrow a query to rows that reference a page (backlinks).
- `DescribesPhpType`: the PHPDoc type templates read for a shared value, such as `'\Carbon\CarbonImmutable|null'`, for `lynx:phpstan-stub`. Without it the field reads as `mixed`.

## Static analysis

Fields are read as magic properties (`$page->starts_on`), which PHPStan cannot see. Generate a stub from the schema and add it to `phpstan.neon`:

```bash
php artisan lynx:phpstan-stub
```

```neon
parameters:
    stubFiles:
        - lynx/phpstan/Page.stub
```

Each field then reads as its type presents it: a `TranslatableValue` for translatable fields, `int|null` for an integer, a `PageArray` for page references (`Page|null` with `multiple: false`), a single image or `null` for an images field with `max_files: 1`. Writes accept any value, as `pages.save` validates them. The stub lists every field, so reading a field the page's template does not have still returns `null` at runtime. Regenerate it after adding fields, for example in the same commit as `lynx:schema:export`.

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

## Worked example: the icon field

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
| `phpType()` (`DescribesPhpType`) | A nullable string, for `lynx:phpstan-stub`. |

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
