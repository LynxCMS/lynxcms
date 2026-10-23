---
title: Languages
description: Add languages to a running site, translate pages and fields, and control fallback and the current language.
---

# Languages

Lynx is multilingual from the start. Every site has a **default language**, and you can add more at any time: a language is a row in a table, not a column, so adding one needs no migration and no deploy. Each translatable field holds one value per language, each page has a URL per language, and when a translation is missing Lynx **falls back** to another language instead of showing nothing.

## Adding a language

`lynx:install` creates the default language with the code `default` and the name "Default". First give it its real code, name and locale, so code and labels can refer to it as `en`:

```php
lynx()->languages->update('default', ['code' => 'en', 'name' => 'English', 'locale' => 'en']);
```

Then add languages in the admin under **Settings › Languages** (it needs the `schema.manage` permission), or in code:

```php
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'en');
lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
```

A language has these settings:

| Setting | Meaning |
| --- | --- |
| `code` | Short code for code, the API and per-language arrays such as labels: lowercase letters, digits, `-` and `_`. Changing it renames the labels stored under the old code. |
| `name` | Display name, such as "Português". |
| `label` | Optional short label for the admin's language tabs ("PT", "pt-BR"). Empty shows the name. |
| `locale` | Locale for Laravel's translations and the HTML `lang` attribute. Defaults to the code. A regional locale (`pt-PT`) also reads the bare language's translations (`lang/pt`). |
| `prefix` | URL prefix. Defaults to the code; the default language has none. |
| `fallback` | Language used when a value is missing. Chains end at the default language, and cycles are refused. |
| `active` | Whether visitors can see the language. |

Adding a language makes the home page available in it at once, so `/pt` works before any other page is translated.

## URLs

The default language has no prefix: `/blog/hello-world`. Every other language has its prefix, and pages have a URL name per language: `/pt/blogue/ola-mundo`. Changing a language's prefix rebuilds its URLs, and the old ones redirect.

A page is **published in a language** once it has a name in that language. Until then, links to it in that language point to its default language URL, so visitors never land on a missing page. A URL that uses another language's names, such as `/pt/blog/hello-world` for a page whose Portuguese URL is `/pt/blogue/ola-mundo`, redirects to the Portuguese address once the page has one.

## Translating pages

Translatable fields hold one value per language. In the admin, each translatable field has language tabs, and the page's **Settings** tab holds its URL name in each language. In code, set the values of another language with `setLanguageValue()`, and the URL name with `setLanguageName()`:

```php
$post = lynx()->pages->get('/blog/hello-world');
$post->setLanguageName('pt', 'ola-mundo');
$post->setLanguageValue('pt', 'title', 'Olá, mundo');
$post->save();
```

Assigning a field directly, `$post->title = 'Hello'`, sets the value in the current language: the request's language on the site, and the default language in Artisan commands, Tinker and queued jobs.

The `lynxcms/ai` package can fill missing translations by machine, marked for review; see [MCP Server and Agent](/ai/).

## Fallback

When a translatable field has no value in the visitor's language, Lynx shows the value of the next language in that language's **fallback chain**. With `pt-br` falling back to `pt`, and `pt` to the default language, a Brazilian visitor sees each field in the first of those languages that has a value.

Fallback happens field by field, when the page is read. Lynx never copies values between languages, so a translation added later shows up at once, and a page can be half translated without anything breaking.

Templates can tell a fallback from a translation:

```php
lynx()->language('pt-br', function () use ($page) {
    $page->title->isFallback();       // true when the value came from pt or the default language
    $page->title->resolvedLanguage(); // the language the value is in
    $page->title->in('pt');           // the stored Portuguese value, without fallback
});
```

`@field` uses this to mark fallback text with the right `lang` attribute (see [Views](/basics/views#printing-fields)).

A translatable field can turn fallback off in its settings ("When a translation is empty: show nothing"), for text that must never appear in another language.

## The current language

On the site, the URL prefix decides the language. On a URL without a prefix, Lynx uses the language the visitor chose before (kept in the session, and on their account when they are signed in), and otherwise the default language. Three settings change this, in `config/lynx.php`:

| Setting | Default | Meaning |
| --- | --- | --- |
| `localization.remember` | `true` | Remember the visitor's language in the session and on the user. |
| `localization.accept_language` | `false` | Use the browser's language on a first visit. |
| `localization.redirect_default_prefix` | `true` | Redirect URLs that carry the default language's prefix, such as `/en/blog`, to `/blog`. |

In code, `lynx()->languages->current()` is the language of the request. To run code in another language, such as sending an email in the recipient's language from a queued job, use `lynx()->language()`:

```php
$title = lynx()->language('pt', fn () => (string) $post->title);
```

## Sites with one language

Until a site has a second language, the admin leaves languages out: the field form doesn't ask whether a field is translatable, and screens don't mention translations, fallbacks or the default language. Fields whose type can be translated are created translatable anyway, because that can't be changed later, so adding a language just works.

`lynx()->languages->isMultilingual()` says whether the site has more than one language, for templates that want to do the same, such as hiding a language switcher.

## Deleting a language

Deleting a language removes every value, page name and URL stored in it. Languages that fell back to it fall back to the default language instead. The default language can't be deleted. Try it as a dry run first, which lists what would be removed:

```php
lynx()->languages->delete('pt-br', dryRun: true);
```
