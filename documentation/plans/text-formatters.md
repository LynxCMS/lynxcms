# Plan: text formatters

Status: built (steps 1 to 5). Every question is decided. Decided with the product owner on 2026-10-08: build ProcessWire style formatters, keep sanitizing HTML on save.

## Why

ProcessWire lets a site builder attach textformatters to a field (Markdown, HTML entities, SmartyPants, video embeds), run in order whenever a template outputs the value. The stored value never changes, so formatters can be added, removed or reordered at any time. Lynx has one fixed step instead: `TranslatableValue::toHtml()` escapes plain text, converts markdown, and passes HTML through, depending on the textarea's `format` setting.

Formatters are about output. They do not replace sanitizing on save (`HtmlCleaner` in the field type's `normalize()`), because the API, MCP and agents read stored values directly and must never receive unsafe HTML.

## Model

* **Field setting `formatters`:** an ordered list of formatter names, for text and textarea fields, e.g. `["markdown", "smartypants"]`. Stored in the field's settings, so schema export and apply carry it. Templates will be able to override it per field, like `label`, in a later version (not v1).
* **Contract** in `lynxcms/contracts`: `Formatter` with `name()`, `description()`, `appliesTo(string $fieldType): bool`, `producesHtml(): bool` and `format(string $text, FormatContext $context): string`. The context carries the field, the language and the page id.
* **Registry:** `lynx()->formatters()->register(SmartyPants::class)`, like field types. Modules ship their own (video embeds, glossary links).
* **Where they run:** when a template outputs a value, as ProcessWire's output formatting does. **Decided:** `{{ $page->body }}` renders the field formatted. Blade's `{{ }}` already prints `Htmlable` values through `toHtml()`, so the chain runs in `toHtml()`, and `@field($page->body)` (which adds `lang` for fallback values) uses the same path.
* **Shared fields too:** translatable text fields already return a `TranslatableValue`; shared text and textarea fields still return a plain string, which `{{ }}` would only escape. They will return a value object as well (the same class, holding the one shared value), so every text field formats the same way.
* **Raw data:** **Decided:** `$page->getUnformatted('body')` returns the stored value as a plain string or array, with no formatting and no escaping, as in ProcessWire (in the current language with fallback; pass a language for one language: `$page->getUnformatted('body', 'pt')`). `$page->body->value()` stays and returns the same. JSON, the API, MCP, agents and schema export always use the stored value.
* **Casting:** `(string) $page->body` and `{!! $page->body !!}` print the stored value unformatted, so code that builds strings never gets formatter output by surprise; use `{{ }}` or `toHtml()` for formatted HTML.
* **The `format` setting stays** and keeps deciding what is stored and which input the editor shows (plain textarea, rich text, Markdown). Its built in conversion becomes the first step of the chain: `plain` escapes, `markdown` converts, `html` passes through; the configured formatters run after it.
* **Safety:** a chain whose result is HTML ends with `HtmlCleaner` when any formatter in it `producesHtml()`, so a formatter cannot reintroduce scripts.
* **Speed:** results are memoized per request by page, field, language and a hash of the value.

## Formatters in core (v1)

| Name | Does |
| --- | --- |
| `smartypants` | Curly quotes, en and em dashes and ellipses, outside tags, attributes, code and pre |
| `paragraphs` | Plain text to `<p>` and `<br>` |
| `markdown` | Markdown to HTML in fields whose `format` is plain (for gradual adoption) |
| `external_links` | `rel="noopener"` and `target="_blank"` on links to other hosts |

Video embeds and image handling come as modules.

## Admin

The field form (templates and fields screens) gets an ordered multi select of the formatters that apply to the field's type, each with its description. The page editor's read only previews show the formatted output.

## Questions

1. **Decided:** `{{ $page->body }}` renders formatted, as in ProcessWire; `$page->getUnformatted('body')` returns the raw stored value.
2. **Decided:** templates can override a field's formatters, like `label`, but not in v1. The override will be a template field setting (`formatters` next to `label` and `required`), so it fits the existing model without changes to it.

## Build order (TDD throughout)

1. **Done.** Contract, registry, `FormatContext`, the chain in `toHtml()` and `FieldRenderer`, the final `HtmlCleaner` pass, memoization; value objects for shared text fields; `getUnformatted()`. ADR-0031 accepted.
2. **Done.** The four core formatters.
3. **Done.** `formatters` as a field setting, validated (unknown names, wrong field type) with errors under `settings.formatters`; schema export and apply carry it with the other settings; `describe_schema` lists the formatters per field type.
4. **Done.** The field form's Formatters section (add, drag to reorder, remove; only formatters for the field's type) and a Preview under editor inputs of fields with formatters, rendered by `lynx()->formatters()->render()`.
5. **Done.** Docs, the agent usage guide and `describe_schema`.
