---
title: Static site export
description: The whole site as HTML files, to host without PHP on GitHub Pages, Netlify, Cloudflare Pages or any web server.
order: 11
---

# Static site export

The static export comes with `lynxcms/static-site`. It writes the site as plain files: every published page in every language as HTML, the public files, a not found page, the sitemap and redirects for old addresses. Those files need no PHP and no database, so any static host can serve them.

Edit as usual in Lynx, then export where the content lives (your own machine, a private server or the live one) and upload the result.

## Export

```bash
php artisan lynx:static                                   # to dist/, for the address in app.url
php artisan lynx:static --url=https://www.example.com     # for the address the static site will have
php artisan lynx:static --path=/tmp/site --zip            # another folder, and a zip beside it
```

The admin has the same under **Settings › Static export**, with a button to download the zip. Through MCP and AI agents it is the `static.export` action. All three need the `static.export` permission.

The command reports what it wrote, the pages it left out and the pages that need PHP for part of what they do.

## What is exported

- Every published page whose template has URLs, hidden pages included, in every language it is live in, as `{path}/index.html`. Hosts then answer both `/about` and `/about/`.
- Pages render as a visitor sees them. Unpublished pages and pages behind a [page password](/docs/front-end/page-passwords) are left out, and so is anything a page answers with other than a normal page.
- `/sitemap.xml` and its parts and `/robots.txt`, when those modules are installed, and any address added to `paths`.
- `404.html`, the site's own not found page. GitHub Pages, Netlify and Cloudflare Pages send it for unknown addresses.
- Everything in `public/`, copied after the pages render so the image sizes they made are included: built assets, media, icons. PHP entry points, `.htaccess` and the page cache's folder are left out. A file in `public/` wins over a route at the same address, as on the live site.
- Scripts, styles and icons that pages load from this site through a route rather than a file in `public/`, such as Livewire's script.

Links, canonical tags, alternates, the sitemap and media URLs (disks served from the app's address, such as `public`) use the static address (`--url`, `lynx-static.url`, then `app.url`). Pages render as production without debug by default, so an export made on your own machine is not marked noindex.

## Redirects

Renamed and moved pages keep their old addresses on a live Lynx site. The export keeps them in two ways:

- a `_redirects` file, which Netlify and Cloudflare Pages answer with a 301;
- a small page at each old address that sends visitors to the new one and names it as canonical, for hosts that read no such file (GitHub Pages, a plain web server or a bucket).

Turn either off with `redirects.file` and `redirects.pages`.

## What needs PHP

The export is what the site sends, without the server behind it. These don't work on a static site, and the report names the pages that have them:

- Forms that post to the site, and Livewire components. Use a form service, or a form written in Alpine or plain JavaScript that posts elsewhere.
- [Islands](/docs/front-end/page-cache#islands), which PHP fills in after the page loads.
- Query strings. A static host answers `/news?page=2` with `/news`, so paginated listings show their first page only.
- Page passwords, search on the server and anything else decided per request.

Alpine and plain JavaScript work as on the live site.

## Deploying

Export again after changes. Scheduled publishing needs an export after the scheduled time too, so schedule one:

```php
// routes/console.php
Schedule::command('lynx:static --url=https://www.example.com')->hourly();
```

Then upload `dist/` with the host's tool. For example, from the server that exports:

```bash
php artisan lynx:static --url=https://www.example.com
npx wrangler pages deploy dist --project-name=example     # Cloudflare Pages
npx netlify deploy --dir=dist --prod                      # Netlify
rsync -a --delete dist/ user@host:/var/www/example/       # any web server
```

To publish with GitHub Pages, commit the export to a repository and let a workflow deploy it:

```yaml
# .github/workflows/pages.yml in the repository that holds the export
name: Pages
on:
  push:
    branches: [main]
permissions:
  contents: read
  pages: write
  id-token: write
jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: github-pages
    steps:
      - uses: actions/checkout@v4
      - uses: actions/upload-pages-artifact@v3
        with:
          path: dist
      - uses: actions/deploy-pages@v4
```

The export folder is replaced as a whole: it is built beside the old one and swapped in when done, so a failed export leaves the last one in place. Lynx only replaces a folder an export made (it holds a `.lynx-static` file) or an empty one, so a wrong `--path` never empties a folder of yours.

## Building in CI

The content can also live in the repository, as with flat file CMSs: export it as a content package folder, commit it, and let CI build the site from it on every push. Editors work in a local or private Lynx and commit the changes.

```bash
php artisan lynx:export content      # the schema, every page as a line of content/pages.jsonl, and their files
git add content && git commit -m "Update content"
```

The workflow builds an empty site, imports the folder and exports it:

```yaml
# .github/workflows/static.yml
name: Static site
on:
  push:
    branches: [main]
jobs:
  build:
    runs-on: ubuntu-latest
    env:
      APP_ENV: production
      APP_DEBUG: 'false'
      LYNX_STATIC_URL: https://www.example.com
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
      - run: composer install --no-dev --optimize-autoloader
      - run: |
          cp .env.example .env
          php artisan key:generate --force
          touch database/database.sqlite
          php artisan migrate --force
          php artisan lynx:install
          php artisan storage:link
          php artisan lynx:import content --with-schema
      - run: npm ci && npm run build
      - run: php artisan lynx:static
      # then deploy dist/: upload it as a Pages artifact, run wrangler, or push it to a branch
```

The export makes the image sizes the templates ask for while it renders, so they need not be committed. Content packages are text apart from the files, so changes show in diffs and pull requests.

## Configuration

`config/lynx-static.php`, published with `php artisan vendor:publish --tag=lynx-static-config`:

| Key | Default | Meaning |
| --- | --- | --- |
| `path` | `dist` in the app (`LYNX_STATIC_PATH`) | Folder the site is exported to. |
| `url` | `null` (`LYNX_STATIC_URL`) | Address the static site is served at; `null` uses `app.url`. |
| `environment` | `production` (`LYNX_STATIC_ENVIRONMENT`) | Environment pages render in, without debug; `null` keeps the app's own. |
| `paths` | `['/sitemap.xml', '/robots.txt']` | Other addresses to export, written as the files they answer with. |
| `exclude` | PHP entry points, `.htaccess`, `hot`, `lynx-cache` | Paths in `public/` that are not copied (patterns). |
| `redirects.file` | `true` | Write `_redirects`. |
| `redirects.pages` | `true` | Write a page at each old address. |
