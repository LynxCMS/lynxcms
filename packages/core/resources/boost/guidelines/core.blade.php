@verbatim
## Lynx CMS

This app uses Lynx, a multilingual CMS on Laravel (`lynxcms/*`). Content lives in pages with fields; languages are database rows.

- Use the `lynx()` helper (or the `Lynx` facade): `lynx()->pages`, `lynx()->templates`, `lynx()->fields`, `lynx()->languages`.
- Never write Lynx tables with Eloquent or the query builder. Every write goes through an action, usually via the API (`$page->save()`, `lynx()->pages->move()`), or `lynx()->run(Action::class, $input)`. Pass `dryRun: true` to preview.
- Do not add per-language columns or copy values between languages: translatable fields fall back to the next language at read time.
- Find pages with selectors: `lynx()->pages->find('template=course, price<300, sort=-title, limit=10')`. Pass `maxLimit:` when the selector comes from a visitor.
- Templates render `resources/views/templates/{template}.blade.php` with `$page`; print values with `@field($page->title)`.
- Schema (languages, fields, templates, roles) is versioned as YAML: `php artisan lynx:schema:export` and `lynx:schema:apply`. Content moves with `lynx:export` and `lynx:import`.
- Prefer moving pages to the trash (`lynx()->pages->trash()`) over deleting them.
@endverbatim
