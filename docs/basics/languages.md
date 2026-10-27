---
title: Languages
description: Add languages to a running site, translate pages and fields, and control fallback and the current language.
---

# Languages

A Lynx site can be in several languages. Each page has its text and its address in every language, and visitors read the site in theirs. You can add a language at any time, even to a live site: it needs no migration (no change to the database's structure) and no deploy. When a page isn't translated yet, visitors see the text of another language you choose, so they never find a blank space.

The examples on this page follow a blog written in English, with Portuguese added later.

## Adding a language

`lynx:install` creates the default language with the code `default` and the name "Default". First give it its real code, name and locale, so your code and labels can call it `en`:

```php
lynx()->languages->update('default', ['code' => 'en', 'name' => 'English', 'locale' => 'en']);
```

Then add languages in the admin under **Settings › Languages** (it needs the `schema.manage` permission), or in code:

```php
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'en');
lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
```

The first line adds Portuguese, written as in Portugal (`pt-PT`), which borrows from English where a text is missing. The second adds Brazilian Portuguese, which borrows from Portuguese first. [Fallback](#fallback) explains how that works.

Adding a language makes the home page available in it at once, so `/pt` works before any other page is translated.

A language has these settings:

| Setting | Meaning |
| --- | --- |
| `code` | Short code for code, the API and per-language arrays such as labels: lowercase letters, digits, `-` and `_`. Changing it renames the labels stored under the old code. |
| `name` | Display name, such as "Português". |
| `label` | Optional short label for the admin's language tabs ("PT", "pt-BR"). Empty shows the name. |
| `locale` | Locale for Laravel's translations and the HTML `lang` attribute. Defaults to the code. A regional locale (`pt-PT`) also reads the bare language's translations: the files in `lang/pt` and the strings in `lang/pt.json`, with `lang/pt-PT.json` winning where both have a string. `trans_choice()` finds them too, and uses the language's plural rules. |
| `prefix` | URL prefix. Defaults to the code; the default language has none. |
| `fallback` | Language used when a value is missing. Chains end at the default language, and cycles are refused. |
| `active` | Whether visitors can see the language. |

## URLs

Each language has its own addresses. The default language has no prefix, every other language starts with its own, and each page has a URL name per language:

| Page | English | Portuguese |
| --- | --- | --- |
| Home | `/` | `/pt` |
| Blog | `/blog` | `/pt/blogue` |
| A post | `/blog/hello-world` | `/pt/blogue/ola-mundo` |

A page is **published in a language** once it has a URL name in that language. Until then, links to it in that language point to its English address, so visitors never land on a missing page.

Visitors who guess an address get there too. `/pt/blog/hello-world`, which mixes the Portuguese prefix with English names, redirects to `/pt/blogue/ola-mundo` once the post has its Portuguese name. Changing a language's prefix rebuilds its URLs, and the old ones redirect.

## Translating pages

Only **translatable** fields, such as a title or a body, have a value per language. **Shared** fields, such as a date or a price, are the same in every language, so they are entered once ([Field Types](/basics/field-types#shared-and-translatable) says which types can be translated).

In the admin, each translatable field has a tab per language, and the page's **Settings** tab holds its URL name in each language.

In code, set another language's values with `setLanguageValue()`, and the URL name with `setLanguageName()`:

```php
$post = lynx()->pages->get('/blog/hello-world');
$post->setLanguageName('pt', 'ola-mundo');
$post->setLanguageValue('pt', 'title', 'Olá, mundo');
$post->save();
```

Assigning a field directly, `$post->title = 'Hello'`, sets the value in the current language: the request's language on the site, and the default language in Artisan commands, Tinker and queued jobs.

With `lynxcms/ai` installed, Lynx can also translate by machine. It fills only the values that are missing, and marks them for an editor to review:

```php
lynx()->pages->translate('/blog/hello-world', 'pt');
```

[AI](/ai/#translation) covers the settings.

## Fallback

The Portuguese post above has a title but no body yet. A Portuguese visitor sees the Portuguese title and the English body, and the moment someone saves the Portuguese body, they see that instead.

Each language names its **fallback**, the language to borrow from, and the fallbacks form a **chain**. Brazilian Portuguese falls back to Portuguese, and Portuguese to English, so a Brazilian visitor sees each field in the first of those three languages that has a value.

Fallback happens field by field, each time a page is read. Lynx never copies values between languages, so there is nothing to keep in sync, and a page can be half translated without anything breaking (which is how most pages are translated).

### Telling a translation from a fallback

`@field` marks text that came from another language with that language's `lang` attribute, so browsers and screen readers pronounce it correctly (see [Views](/basics/views#printing-fields)). To check it yourself:

```php
lynx()->language('pt-br', function () use ($page) {
    $page->title->isFallback();       // true when the value came from pt or English
    $page->title->resolvedLanguage(); // the language the value is in
    $page->title->in('pt');           // the stored Portuguese value, without fallback
});
```

When no language in the chain has a value, the field reads as `null`, like an empty field, even if a language outside the chain has one. To read that language anyway, ask for it by name: `$page->getLanguageValue('fr', 'title')`.

### Turning fallback off

A translatable field can turn fallback off in its settings ("When a translation is empty: show nothing"). Use it for text that must never appear in another language, such as a legal notice that differs by country.

## The current language

On the site, the URL prefix decides the language: `/pt/blogue` is Portuguese. On a URL without a prefix, Lynx uses the language the visitor chose before (kept in the session, and on their account when they are signed in), and otherwise the default language. Three settings change this, in `config/lynx.php`:

| Setting | Default | Meaning |
| --- | --- | --- |
| `localization.remember` | `true` | Remember the visitor's language in the session and on the user. |
| `localization.accept_language` | `false` | Use the browser's language on a first visit. |
| `localization.redirect_default_prefix` | `true` | Redirect URLs that carry the default language's prefix, such as `/en/blog`, to `/blog`. |

In code, `lynx()->languages->current()` is the language of the request. To run code in another language, use `lynx()->language()`: it switches the language for the callback, and back afterwards.

```php
$title = lynx()->language('pt', fn () => (string) $post->title);
```

### Mail in the reader's language

To send a subscriber a new post in the language they read, build the mail inside `lynx()->language()`. The mailable reads the post's title and URL in its constructor, so a queued mail, which a worker sends later outside that language, keeps them:

<<< @/snippets/advanced/NewPostMail.php#mailable{php}

<<< @/snippets/advanced/language-mail.php#send{php}

## Sites with one language

Until a site has a second language, the admin leaves languages out: the field form doesn't ask whether a field is translatable, and screens don't mention translations, fallbacks or the default language.

Fields whose type can be translated are still created translatable, because that can't be changed later. When the site gets its second language, every text is ready to translate without changing a field.

`lynx()->languages->isMultilingual()` says whether the site has more than one language, for templates that want to do the same, such as hiding a language switcher.

## Deleting a language

Deleting a language removes every value, page name and URL stored in it. Languages that fell back to it fall back to the default language instead. The default language can't be deleted. Try it as a dry run first, which lists what would be removed:

```php
lynx()->languages->delete('pt-br', dryRun: true);
```
