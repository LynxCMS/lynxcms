---
title: Artisan commands
description: Every lynx:* command.
order: 2
---

# Artisan commands

| Command | Does |
| --- | --- |
| `lynx:install --user= --migrate` | Default language, root page and roles. When no one is a superuser, offers to create one; `--user` (an id or email) makes an existing account one instead. |
| `lynx:superuser --email= --name= --password=` | Creates a superuser's account, or makes the account with that email one. Asks for what the options don't give. Comes with `lynxcms/admin`. |
| `lynx:migrate --force` | Lists the database changes waiting in Lynx packages, asks whether the database is backed up, then applies them; `--force` skips the question. |
| `lynx:languages` | Lists the languages. |
| `lynx:find "selector" --lang= --sql` | Runs a selector; `--sql` shows the query. |
| `lynx:schema:export --path=` | Writes the schema as YAML (default `lynx/schema`). |
| `lynx:schema:apply --path= --dry-run --force` | Applies schema files; `--force` also deletes what the files lack. |
| `lynx:phpstan --path=` | Writes the field types Lynx's PHPStan extension reads, so `$page->field` and `$item->field` are checked (default `lynx/phpstan/types.php`). |
| `lynx:export path --without-files` | Writes a content package (a directory or a `.zip`); `--without-files` leaves media files out, for machines that [download them from production](/deeper/live-site#media-from-production). |
| `lynx:import path --with-schema --prune --dry-run` | Imports a content package. |
| `lynx:backup` | Writes a content backup and keeps the newest ones. |
| `lynx:schedule:run` | Applies due publishing schedules (the scheduler runs it every minute). |
| `lynx:clean-html --field= --dry-run` | Cleans stored HTML the way a save would. |
| `lynx:search-index --template=` | Rebuilds the search index, for every page or one template's pages. |
| `lynx:sort-keys` | Rebuilds the stored sort values of the fields in `lynx.sort_keys`, after adding one or changing a field's fallback. See [Finding Pages](/basics/selectors#large-sites). |
| `lynx:trigram` | Installs PostgreSQL's `pg_trgm` and adds trigram indexes that speed up text selectors. See [Finding Pages](/basics/selectors#large-sites). |
| `lynx:strings:scan` | Lists the editable strings each template's view declares with `@string`, and reports problems. Templates without a view, such as settings pages and menus, are counted, not listed. See [Views](/basics/views#editable-texts). |
| `lynx:media:prune --older-than= --dry-run` | Deletes media files that no page, draft or revision uses (the scheduler runs it daily). Needs `lynxcms/media`. |
| `lynx:media:pull --page=` | Queues downloads from production for the media files this machine lacks (`LYNX_MEDIA_REMOTE`). Needs `lynxcms/media`. |
| `lynx:forms:purge --days=` | Deletes old spam and archived form submissions with their files. Needs `lynxcms/forms`. |
| `lynx:sitemap --clear --warm` | Lists the sitemap's parts; `--clear` drops the cached files, `--warm` builds them all now. Needs `lynxcms/sitemap`. |
| `lynx:page-cache --clear --prune --warm --stats --compile` | Drops every stored page, drops expired ones (scheduled every minute), stores every public page now, counts them per template, or writes the settings `serve.php` reads. Needs `lynxcms/cache`. |
| `lynx:static --path= --url= --zip` | Exports the site as static files, to host without PHP. See [Static site export](/modules/static-site). Needs `lynxcms/static-site`. |
| `lynx:page-cache:server nginx` | Prints web server rules (nginx, apache or caddy) that send stored pages without PHP. Needs `lynxcms/cache`. |
| `lynx:ask "prompt" --file= --apply` | Asks the agent to change the site. |
| `lynx:demo --pages= --languages=` | Builds demo content, for trying Lynx and for benchmarks. |
| `lynx:bench --lang= --runs=` | Measures selector and page loading times. |
| `mcp:start lynx` | Starts the MCP server over stdio. |
