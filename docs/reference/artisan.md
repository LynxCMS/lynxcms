---
title: Artisan commands
description: Every lynx:* command.
order: 2
---

# Artisan commands

| Command | Does |
| --- | --- |
| `lynx:install --user= --migrate` | Default language, root page and roles; `--user` makes that user a superuser. |
| `lynx:languages` | Lists the languages. |
| `lynx:find "selector" --lang= --sql` | Runs a selector; `--sql` shows the query. |
| `lynx:schema:export --path=` | Writes the schema as YAML (default `lynx/schema`). |
| `lynx:schema:apply --path= --dry-run --force` | Applies schema files; `--force` also deletes what the files lack. |
| `lynx:phpstan-stub --path=` | Writes a PHPStan stub that types `$page->field` reads (default `lynx/phpstan/Page.stub`). |
| `lynx:export path` | Writes a content package (a directory or a `.zip`). |
| `lynx:import path --with-schema --prune --dry-run` | Imports a content package. |
| `lynx:backup` | Writes a content backup and keeps the newest ones. |
| `lynx:schedule:run` | Applies due publishing schedules (the scheduler runs it every minute). |
| `lynx:clean-html --field= --dry-run` | Cleans stored HTML the way a save would. |
| `lynx:search-index --template=` | Rebuilds the search index, for every page or one template's pages. |
| `lynx:sitemap --clear --warm` | Lists the sitemap's parts; `--clear` drops the cached files, `--warm` builds them all now. Needs `lynxcms/sitemap`. |
| `lynx:page-cache --clear --prune --warm --stats --compile` | Drops every stored page, drops expired ones (scheduled every minute), stores every public page now, counts them per template, or writes the settings `serve.php` reads. Needs `lynxcms/cache`. |
| `lynx:static --path= --url= --zip` | Exports the site as static files, to host without PHP. See [Static site export](/docs/front-end/static-site). Needs `lynxcms/static-site`. |
| `lynx:page-cache:server nginx` | Prints web server rules (nginx, apache or caddy) that send stored pages without PHP. Needs `lynxcms/cache`. |
| `lynx:ask "prompt" --file= --apply` | Asks the agent to change the site. |
| `lynx:demo --pages= --languages=` | Builds demo content, for trying Lynx and for benchmarks. |
| `lynx:bench --lang= --runs=` | Measures selector and page loading times. |
| `mcp:start lynx` | Starts the MCP server over stdio. |
