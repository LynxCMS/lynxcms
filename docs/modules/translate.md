---
title: Translate
description: Machine translation in the admin, into each page's draft, with DeepL, a local model or a cloud model.
order: 10
draft: true
---

# Translate

The translate module comes with `lynxcms/translate`, a paid module. It lets editors translate a page with one click: they choose the languages, see how much text will go, and the empty fields fill in the page's [draft](/basics/publishing#drafts). Visitors see nothing until someone reads the translation and publishes the draft.

It works with three kinds of translation service:

| Service | Good for | Costs |
| --- | --- | --- |
| [DeepL](#deepl) | Natural prose in the languages it knows | Per character; 500,000 free each month |
| [A local model](#a-local-model) | Content that must not leave your servers | Your own hardware; nothing per character |
| [A cloud model](#a-cloud-model) | Languages DeepL lacks, and instructions about tone | Per token, with your AI provider |

You can set up more than one and let editors pick per translation.

## Installing

Your license explains how to add the module's package repository to `composer.json`. Then install it, back up the database, and apply its migrations:

```bash
composer require lynxcms/translate
php artisan lynx:migrate
```

The module needs Lynx 0.1.41 or later, with the admin, and a site with at least two [languages](/basics/languages). Set up at least one service below: until then, the Translate buttons stay hidden.

## Choosing a service

Put the settings in `.env`, or type them later in **Settings › Machine translation**, which keeps them in the database over `.env`.

### DeepL

```dotenv
DEEPL_API_KEY=your-key
```

A key ending in `:fx` uses DeepL's free plan. HTML keeps its tags and links, since DeepL is told which texts are HTML.

### A local model

Any service with an OpenAI-compatible chat API works: Ollama, LM Studio, llama.cpp or vLLM. With [Ollama](https://ollama.com) and the `qwen3:8b` model:

```dotenv
LYNX_TRANSLATE_ENDPOINT_URL=http://localhost:11434/v1
LYNX_TRANSLATE_ENDPOINT_MODEL=qwen3:8b
LYNX_TRANSLATE_ENDPOINT_REASONING=none
```

`LYNX_TRANSLATE_ENDPOINT_REASONING=none` skips the thinking step of models such as qwen3, which makes a page about ten times faster. Leave it out for a service that refuses the setting.

Small models make more mistakes than DeepL, so read their drafts with care. A translation whose HTML tags changed is left out, so that field stays empty for a person instead of breaking the page.

### A cloud model

Install [`lynxcms/ai`](/ai/) and set its provider and key. It shows in the dialog as "AI model".

## Translating a page

In the page editor, **Translate** opens a dialog:

1. Choose **From** and **Into**. The dialog counts the texts and characters it will send. Counting sends nothing, so it costs nothing.
2. Choose the service, if the site has more than one.
3. Press **Translate into draft**. Each field shows its progress as it goes.

On a site with a queue worker, a whole page is translated in the background: the dialog closes at once, and the editor tells you when the draft is ready. If you leave the page meanwhile, **Translations** in the sidebar shows a mark until you look at the result. A few fields, or a site without a worker, are translated while you wait.

The editor then shows the draft. Read the translation in each field's language tab, or open the draft's preview link beside the title, and publish the draft when it reads well.

Only fields with no text in the target language are filled. **Also replace earlier machine translations** replaces text a service wrote before; text a person wrote is never replaced.

### One field at a time

Under each field, in every language but the default, a line says how its text got there:

- **Machine translation**: a service wrote it. Saving it by hand makes it yours, and the mark goes.
- **Source changed**: the text it was translated from has changed since, so the translation may be out of date.

**Translate** (on an empty field) or **Translate again** (over a machine's text) opens the dialog for that field alone. Fields a person wrote offer neither.

### What gets translated

Besides text fields, a translation fills what is missing in:

- the text fields of each item in a repeater or matrix;
- the descriptions of pictures in an images field;
- the options of a form question;
- the page's own template texts (`@string`).

These keep no record of who wrote them, so they are only ever filled when empty. A template text whose translation loses a placeholder such as `:name` is left for a person, since the template would show it wrong.

## Many pages at once

**Translations** in the sidebar translates every page, the pages of one template, or a page and the pages below it. **Count what will be sent** shows the pages, texts and characters first. **Translate into drafts** starts a run: each page goes into its own draft.

- With a queue worker (`QUEUE_CONNECTION=database` or `redis`, and `php artisan queue:work`), every page is a background job, and the screen follows their progress.
- Without one (`QUEUE_CONNECTION=sync`), the screen translates five pages per click (`LYNX_TRANSLATE_SYNC_PAGES`), so a request never runs for minutes.

A page someone is editing, or that you may not translate, is left out with the reason. A page the service fails on is listed with the error, and the run goes on. **Cancel the rest** stops a run.

## Finding outdated translations

The page tree's **Details** view gains a filter for each language, such as **Outdated in Português**: pages with a machine translation whose source text has changed since, on the page or in its draft. Open one, and the fields marked **Source changed** show what to translate again.

In code, the same question is a [selector](/basics/selectors) clause: `has_outdated=pt`.

## Site texts

Site texts show on every page and have no draft, so **Site texts › Translate** shows the translations first. Edit any of them, then **Save these texts**. A translation missing one of its source's placeholders is held back until you put the placeholder back.

## Settings, budget and glossary

**Settings › Machine translation** keeps, over `.env`:

- **Services**: the DeepL key, and the local model's address, model, key and thinking step. Keys are encrypted with the app's key and never shown again; leave a key's field empty to keep it.
- **Style**: whether to address readers formally or informally, and one sentence about the site, which models use to choose their words.
- **Monthly budget**: a limit in characters, with this month's use. Every translation counts, whichever service, screen, agent or MCP client sends it. Past the limit, translations stop with a message until someone raises it, so a run over the whole site can't surprise anyone with the bill.
- **Glossary**: terms to translate one way every time, for each language pair, such as product names to keep as they are. DeepL gets them as a DeepL glossary, made again when the terms change; models get them in their instructions.

If you change `APP_KEY`, list the old one in `APP_PREVIOUS_KEYS`, or type the keys again: the saved ones were encrypted with it.

## Who may translate

| Permission | Lets people | Notes |
| --- | --- | --- |
| `translate.use` | Translate pages, run bulk translations, translate site texts | Needs `pages.edit` on the same pages; can be limited to a branch or a template |
| `translate.manage` | Change the settings, the budget and the glossary | |

Give them to roles in **Access › Roles**. Superusers have both. See [Permissions](/admin/permissions).

## In code

The module adds its services to Lynx's own translation, so [`pages.translate`](/ai/#translation) can use them by name:

```php
lynx()->pages->translate('/products/ledger', 'pt', draft: true, provider: 'deepl');
```

The names are `deepl`, `local` (or `LYNX_TRANSLATE_ENDPOINT_NAME`) and, with `lynxcms/ai`, `laravel-ai`. Translations from code, agents and MCP clients count against the budget and get the glossary too.

## Configuration

| Setting | Default | Meaning |
| --- | --- | --- |
| `LYNX_TRANSLATE_PROVIDER` | | The service the dialog offers first. |
| `DEEPL_API_KEY` | | DeepL's key. |
| `DEEPL_API_URL` | from the key | Another DeepL address. |
| `LYNX_TRANSLATE_ENDPOINT_URL` | | The local model's OpenAI-compatible address. |
| `LYNX_TRANSLATE_ENDPOINT_MODEL` | | The model's name. |
| `LYNX_TRANSLATE_ENDPOINT_KEY` | | A key, if the service needs one. |
| `LYNX_TRANSLATE_ENDPOINT_NAME` | `local` | The service's name in code. |
| `LYNX_TRANSLATE_ENDPOINT_REASONING` | | `none` skips a model's thinking step. |
| `LYNX_TRANSLATE_ENDPOINT_TIMEOUT` | `300` | Seconds to wait for a model. |
| `LYNX_TRANSLATE_ENDPOINT_BATCH` | `6000` | Characters per request to a model. |
| `LYNX_TRANSLATE_MONTHLY_LIMIT` | `0` | Characters per month; `0` for no limit. |
| `LYNX_TRANSLATE_FORMALITY` | | `more` or `less`. |
| `LYNX_TRANSLATE_CONTEXT` | | A sentence about the site. |
| `LYNX_TRANSLATE_SYNC_PAGES` | `5` | Pages per click without a queue worker. |

Publish the file to change more: `php artisan vendor:publish --tag=lynx-translate-config`.

## When something goes wrong

| Message | What to do |
| --- | --- |
| "the translation quota for this period is used up" | DeepL's own monthly limit. Wait for the next period, or move to a paid plan. |
| "this month's translation budget is used up" | The site's budget. Raise it in **Settings › Machine translation**. |
| "the answer was not the JSON asked for" | A local model wandered off. Try again, or use a larger model. |
| "could not reach `http://localhost:11434/v1`" | The local model isn't running: start Ollama or LM Studio. |
| A field stays empty after a translation | Its HTML or a placeholder came back changed, so it was left for a person. |
