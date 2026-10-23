---
title: Languages
description: Add languages at runtime, set fallback chains, URL prefixes and tab labels.
order: 1
---

# Languages

Languages are rows in `lynx_languages`. Adding one is a single insert: no migration and no deploy.

```php
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');
lynx()->languages->update('pt', ['label' => 'PT']);
lynx()->languages->delete('pt-br', dryRun: true);
```

| Setting | Meaning |
| --- | --- |
| `code` | Short code used by code and the API: lowercase letters, digits, `-` and `_`. |
| `name` | Display name, such as "Português". |
| `label` | Optional short label for the admin's language tabs ("PT", "pt-BR"). Empty shows the name. |
| `locale` | Locale for Laravel and the HTML `lang` attribute. Defaults to the code. A regional locale (`pt-PT`) also reads the bare language's translations (`lang/pt`), then the app's fallback. |
| `prefix` | URL prefix. Defaults to the code; the default language has none. Changing it rebuilds that language's URLs, and the old ones redirect. |
| `fallback` | Language used when a value is missing. Chains end at the default language, and cycles are refused. |
| `active` | Whether the language is visible on the site. |

The admin manages languages under **Settings > Languages** (needs `schema.manage`).

Adding a language makes the home page active in it at once, so `/pt` works before any other page has a Portuguese name.

A URL that uses another language's names, such as `/pt/courses/risk` for a page whose Portuguese URL is `/pt/cursos/risco`, redirects to the page in the URL's language when the page is published in it.

## Sites with one language

Until a second language is added, the admin leaves languages out: the field form has no Translatable choice, and screens don't mention translations, fallbacks or the default language. Fields of types that can be translated (text, textarea, URL) are created translatable anyway, because that can't be changed later, so adding a language just works. `lynx()->languages->isMultilingual()` says whether the site has more than one language, for templates and modules that want to do the same.

## Fallback

Fallback happens when a value is read, field by field. With `pt-br` falling back to `pt`, then to the default language, a Brazilian visitor sees each field in the first language of that chain that has a value.

```php
lynx()->language('pt-br', function () use ($page) {
    $page->title->isFallback();       // true when the value came from pt or the default language
    $page->title->resolvedLanguage(); // the language the value is in
    $page->title->in('pt');           // the stored Portuguese value, without fallback
});
```

A translatable field can turn fallback off in its settings, so an empty translation shows nothing.

## Deleting a language

Deleting removes every value, page name and URL stored in that language. Languages that fell back to it fall back to the default instead. The default language cannot be deleted.

## The current language

On the site, the URL prefix picks the language. Without a prefix, Lynx uses the session, the signed in user's language, then the default language. See `lynx.localization` in [Configuration](/reference/configuration).
