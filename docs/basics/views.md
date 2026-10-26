---
title: Views
description: Render pages with Blade, print fields, list pages, switch languages, render blocks and use your own routes.
---

# Views

Lynx renders pages with Blade, the way the rest of your Laravel app renders its views. This page shows where a page's view lives, how to print its fields, and the Blade helpers Lynx adds.

## How a page is rendered

Lynx registers one catch-all route after your app's routes. For each request it reads the language from the URL prefix, finds the page by its path, checks that the visitor may see it, and renders the view named after the page's template:

| Template | View |
| --- | --- |
| `post` | `resources/views/templates/post.blade.php` |
| `blog` | `resources/views/templates/blog.blade.php` |
| `home` | `resources/views/templates/home.blade.php` |

The view receives the page as `$page`. A template without a view renders with Lynx's own `lynx::page`, which lists the page's fields, so a new template shows something before you write its view.

```blade
{{-- resources/views/templates/post.blade.php --}}
<x-layout :page="$page">
    <h1>@field($page->title)</h1>
    @field($page->body)
</x-layout>
```

Layouts, components, `@include` and the rest of Blade work as usual. [Your First Site](/getting-started/first-site#writing-views) builds a layout component step by step.

## Printing fields

Fields are properties of the page: `$page->title`, `$page->published_on`, `$page->related`.

Print text fields with **`@field`**:

```blade
<h1>@field($page->title)</h1>
@field($page->body)
```

`@field` escapes plain text and prints the cleaned HTML of rich text fields. When a value comes from another language, because the page isn't translated yet, it wraps the value in an element with that language's `lang` attribute, so browsers, search engines and screen readers read it correctly. `{{ $page->title }}` also prints the value, without that marking.

Values are made to be tested in templates:

- An empty text field reads as `null`, so `@if ($page->subtitle)`, `$page->subtitle ?: 'None'` and `??` work. A filled text field is a value object; cast it to compare it with a string: `(string) $page->subtitle === 'News'`.
- A page reference that holds one page reads as that page or `null`: `{{ $page->category?->title }}`. One that holds several reads as a list of pages.
- A field the page's template doesn't have reads as `null`, so partials shared by several templates can use `$page->subtitle ?? ''` safely.
- In debug mode (`APP_DEBUG`), reading a field that doesn't exist at all, usually a typo such as `$page->titel`, writes a warning to the log.

[Field Types](/basics/field-types) shows what each type reads as: dates are Carbon dates, choices print their label, images have sizes, and so on.

## Page details and links

Besides its fields, a page has:

| Property or method | Gives |
| --- | --- |
| `$page->url` | Its URL in the current language. |
| `$page->localUrl('pt')` | Its URL in another language. |
| `$page->name()` | Its URL name in the current language. |
| `$page->parent()`, `$page->children()` | The page above it, and the pages below it. |
| `$page->id` | Its id. |

To link to another page, find it by its path:

```blade
<a href="{{ lynx()->pages->get('/contact')->url }}">Contact</a>
```

Paths are written in the default language, so `/contact` finds the page even when the visitor reads the Portuguese site, and `->url` gives the Portuguese address.

## Listing pages

Use a [selector](/basics/selectors) to list pages. `$page->children()` lists the pages under a page, and `lynx()->pages->find()` searches the whole site:

```blade
@foreach ($page->children('sort=-published_on, limit=10') as $post)
    <a href="{{ $post->url }}">@field($post->title)</a>
@endforeach

@foreach (lynx()->pages->find('template=event, starts_on>=today, sort=starts_on, limit=3') as $event)
    <p>@field($event->title), {{ $event->starts_on->isoFormat('D MMMM') }}</p>
@endforeach
```

Both return a `PageArray`, which you can loop, count and filter. It also joins values without the pitfalls of Laravel's `implode()` on field values:

```blade
{{ $page->topics->implode(', ', 'title') }}
{{ $pages->filter(fn ($page) => $page->featured)->implode(' · ', fn ($page) => $page->name()) }}
```

Lists leave out unpublished and hidden pages, and pages the visitor may not see. Fields load the first time a template reads them, in one query for the whole list, so listing fifty pages with their summaries costs one query for the summaries, not fifty.

## Languages

The request's language decides which value of each translatable field you get, and which URLs pages have.

```blade
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>
    @hreflang($page)
</head>
```

`@hreflang($page)` adds an alternate link for every language the page is published in, for search engines. A language switcher links to the page in each of those languages:

```blade
@if (lynx()->languages->isMultilingual())
    <nav aria-label="Languages">
        @foreach (lynx()->languages->switcher($page) as $link)
            <a href="{{ $link['url'] }}" @if ($link['isCurrent']) aria-current="page" @endif>
                {{ $link['language']->name }}
            </a>
        @endforeach
    </nav>
@endif
```

The switcher keeps the query string, such as filters or a draft preview, while `@hreflang` leaves it out, so search engines see one URL per language. [Languages](/basics/languages) covers fallback and the current language.

## Matrix blocks

A [matrix field](/basics/field-types#matrix) holds typed blocks. `@matrix` renders each block with the view of its type:

```blade
@matrix($page->blocks)
```

A `quote` block on a page that uses the `project` template renders `resources/views/templates/fields/blocks/project/quote.blade.php` if it exists, and otherwise `templates/fields/blocks/quote.blade.php`. The block's view receives `$block`, `$page` and `$loop`:

```blade
{{-- resources/views/templates/fields/blocks/quote.blade.php --}}
<blockquote>
    @field($block->quote)
    <cite>{{ $block->cite }}</cite>
</blockquote>
```

With `APP_DEBUG` on, a block without a view leaves an HTML comment naming the view to create. For full control, loop over `$page->blocks` yourself; `$block->type()` gives each block's slug.

## Livewire components

Pages render with Blade, so a template can use any [Livewire](https://livewire.laravel.com) component of your app, like any other Blade view:

```blade
<livewire:newsletter-signup />
<livewire:course-filter :page-id="$page->id" />
```

Components on Lynx pages can use the `LynxCms\Core\Livewire\UsesLynxPage` trait. Livewire sends its updates to its own URL, without the page's language prefix; the trait remembers the visitor's language and restores it on every update, so `__()` and page fields stay in that language. Pass the page's id as `page-id`, as above, and `$this->page()` returns the page:

```php
use Livewire\Component;
use LynxCms\Core\Livewire\UsesLynxPage;

final class CourseFilter extends Component
{
    use UsesLynxPage;

    public function render()
    {
        return view('livewire.course-filter', [
            'courses' => $this->page()?->children('template=course, sort=title'),
        ]);
    }
}
```

### Components editors choose

A [`component` field](/basics/field-types#component) lets editors place one of your registered Livewire components on a page and fill in its settings. Render it with `@lynxComponent`, giving the page and the field's name:

```blade
@lynxComponent($page, 'enrol')
```

## Editable texts

Short texts that belong to the design, such as a headline or a button label, can be declared in the view and changed by editors without a field:

```blade
<h1>@string('hero.title', 'We make films', label: 'Hero title')</h1>
<a href="/contact">@string('cta.contact', 'Get in touch')</a>
<p>@string('hero.intro', 'We tell stories with **pictures**.', format: 'markdown')</p>
<footer>@string('footer.note', '© Example Studio', scope: 'site')</footer>
```

The text in the view is the default. Editors change it per language in the page's **Texts** tab, or under **Site texts** for strings with `scope: 'site'`, which every page shares. Strings in views under a `layouts` folder are site strings unless they say `scope: 'page'`, so a layout's footer note is one text for the whole site.

A default can also be one text per language: `@string('hero.title', ['en' => 'We make films', 'pt' => 'Fazemos filmes'])`. Lynx stores only the texts editors change, so a new default in the view reaches every page nobody edited. Keys and texts must be written out literally. `php artisan lynx:strings:scan` lists every string the views declare and reports problems.

### Values in texts

A text that holds a value, such as a name or a time, marks it with a placeholder, as Laravel's translations do, and `replace:` gives the values. Editors move the placeholder where their language wants it:

```blade
<p>@string('rooms.free', ['en' => ':room is free until :time.', 'fr' => 'La salle :room est libre jusqu\'à :time.'], replace: ['room' => $room->title, 'time' => $until])</p>
```

`:Room` capitalises the value and `:ROOM` upper-cases it. Values are escaped like the text. `replace:` is the one argument that may be code; the Texts tab lists the placeholders each text uses and asks editors to keep them.

### Texts in Livewire components

A template that places a Livewire component, with `<livewire:book-a-room />` or `@livewire('book-a-room')`, also declares the strings of the component's view, `livewire/book-a-room.blade.php` by Livewire's convention, so they show in the page's Texts tab. Page texts need the page: give the component's view a `$page` variable, for example by passing the page's id to the component and loading it in `render()`. Without one, page texts show their default.

A page's own texts are part of the page: while it has a draft, the Texts tab edits the draft, and revisions keep and restore them. Site texts are shared, so they are always live.

## The settings page

Site-wide content that editors change, such as a footer text or social links, belongs on a settings page: a template named `settings`, with `has_url: false` and `singleton: true`, and its one page. Read it anywhere with `lynx()->settings()`:

```blade
<footer>@field(lynx()->settings()?->footer_text)</footer>
```

`lynx()->settings('shop_settings')` reads the page of another singleton template.

## The page not found

A URL that matches no page shows the **page not found** with status 404: a page in the tree, so editors write its text like any other page's, in every language. `lynx:install` creates it at `/not-found/` with a `not-found` template that only has a title. It is a system page: it can't be deleted or moved, and it stays out of lists, searches and the sitemap.

Give it a view like any template, and add fields to the template for whatever the page should say:

```blade
{{-- resources/views/templates/not-found.blade.php --}}
<x-layout :page="$page">
    <h1>{{ $page->title }}</h1>
    @field($page->body)
    <a href="/">Back to the home page</a>
</x-layout>
```

Superusers can pick another page, or none, under **Settings > General** in the admin, which marks the chosen page in the tree. Without one, and for JSON requests and the admin's own screens, Laravel's `errors/404` view answers as usual. If the page's view fails, the error is reported and Laravel's 404 answers instead. The same choice is the `pages.set_not_found` action:

```php
use LynxCms\Core\Actions\Pages\SetNotFoundPage;

lynx()->run(SetNotFoundPage::class, ['page' => '/oops/']); // or null for none
```

Sites installed before this existed get the page the next time `php artisan lynx:install` runs; it does nothing once someone has chosen a page, or none.

## Drafts and old URLs

Editors preview a page's draft at its URL with `?preview=draft`; templates need no changes for it. When an editor renames or moves a page, its old URLs redirect to the new one, in every language. See [Publishing](/basics/publishing).

## Using your own routes

Lynx's route serves every URL your routes leave free, so a controller of your own at `/account` keeps working. To serve pages entirely from your own routes, turn Lynx's route off with `LYNX_ROUTES=false` and load pages yourself:

```php
Route::get('/news/{name}', function (string $name) {
    $post = lynx()->pages->get("/news/{$name}") ?? abort(404);

    return view('templates.post', ['page' => $post]);
});
```

Livewire components on these pages work as described in [Livewire components](#livewire-components).
