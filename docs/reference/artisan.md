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
| `lynx:templates template --json` | Lists the templates with their pages, fields and whether they have a view, the site's first and those Lynx and its modules own (menus, trash, settings) in a second table with their owner; with a template, its fields in order with their type, section and tab. Tables wrap to fit the terminal. |
| `lynx:fields --unused --json` | Lists the fields with their type and where they are used: templates, and the items of repeaters and matrices, with module fields in a table of their own; `--unused` keeps those nothing uses. With `--json`, each template or field has an `owner`, `null` for the site's. |
| `lynx:doctor --json` | Looks for problems in the site's setup and structure, each with a fix. See [Checking a site](#checking-a-site). |
| `lynx:find "selector" --lang= --sql` | Runs a selector; `--sql` shows the query. |
| `lynx:schema:export --path=` | Writes the schema as YAML (default `lynx/schema`). |
| `lynx:schema:apply --path= --dry-run --force` | Applies schema files; `--force` also deletes what the files lack. |
| `lynx:phpstan --path=` | Writes the field types Lynx's PHPStan extension reads, so `$page->field` and `$item->field` are checked (default `lynx/phpstan/types.php`). |
| `lynx:ide-helper --path=` | Writes `_ide_helper_lynx.php`, so editors autocomplete `$page->field` and `$item->field` with their types. See [Autocomplete in your editor](/deeper/custom-field-types#autocomplete-in-your-editor). |
| `lynx:export path --without-files` | Writes a content package (a directory or a `.zip`); `--without-files` leaves media files out, for machines that [download them from production](/deeper/live-site#media-from-production). |
| `lynx:import path --with-schema --prune --dry-run` | Imports a content package. |
| `lynx:backup` | Writes a content backup and keeps the newest ones. |
| `lynx:schedule:run` | Applies due publishing schedules (the scheduler runs it every minute). |
| `lynx:clean-html --field= --dry-run` | Cleans stored HTML the way a save would. |
| `lynx:search-index --template=` | Rebuilds the search index, for every page or one template's pages. |
| `lynx:sort-keys` | Rebuilds the stored sort values of the fields in `lynx.sort_keys`, after adding one or changing a field's fallback. See [Finding Pages](/basics/selectors#large-sites). |
| `lynx:trigram` | Installs PostgreSQL's `pg_trgm` and adds trigram indexes that speed up text selectors. See [Finding Pages](/basics/selectors#large-sites). |
| `lynx:make:view template --all --layout= --all-tabs --force` | Writes a starting view for a template, or every template whose pages have none, with its fields printed. See [Views](/basics/views#starting-a-view). |
| `lynx:strings:scan` | Lists the editable strings each template's view declares with `@string`, and reports problems. Templates without a view, such as settings pages and menus, are counted, not listed. See [Views](/basics/views#checking-your-strings). |
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

## Checking a site

When a page doesn't show what you expect, start with `lynx:doctor`:

```bash
php artisan lynx:doctor
```

It runs a list of checks and prints OK for each that passes, or what it found and how to fix it:

- **Template views:** a template whose pages have URLs but no view of their own. Those pages show in Lynx's plain fallback view.
- **Unused fields:** a field no template uses, directly or inside a repeater's or matrix's items.
- **Orphaned values:** values kept for fields a page's template no longer has. Lynx never deletes them by itself, and `pages.purge_orphans` does.
- **Scheduler:** scheduled publishing more than ten minutes late, which means Laravel's scheduler (the cron entry) is not running.
- **Global middleware:** middleware Lynx runs on every request that could not be added, because the app's HTTP kernel is not Laravel's own. The fix names the line to add in `bootstrap/app.php`.
- **Storage link** (with `lynxcms/media`): the public media disk has no `storage:link`, so images and files are broken links.

Warnings are worth a look; errors break something visitors or editors rely on. The command fails (exit code 1) only on an error, so a deploy script can run it after each release and stop on one. `--json` prints the report as JSON, for scripts and AI agents.

A module adds its own checks. A check implements `LynxCms\Core\Doctor\Check`: a key, a label, and a `run()` that returns the findings, empty when all is well. It only reads.

```php
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\DoctorChecks;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;

final class MailerIsSet implements Check
{
    public function key(): string { return 'mailer'; }

    public function label(): string { return 'Forms can send email'; }

    public function run(): array
    {
        return config('mail.default') === 'log'
            ? [new Finding(Severity::Warning, 'Mail goes to the log.', 'Set MAIL_MAILER in .env.')]
            : [];
    }
}

// In the module's service provider, in boot():
$this->app->make(DoctorChecks::class)->add(MailerIsSet::class);
```
