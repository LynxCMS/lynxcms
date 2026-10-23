# Atlas Training, a Lynx starter

A bilingual (English and Portuguese) site for a wellness school, built with [Lynx CMS](https://lynxcms.org/cms/docs/) and BlatUI's Bloom template. It is the finished code of the guide [Build a Training Company Site](https://lynxcms.org/cms/docs/guides/training-site/), so each part is explained there.

This repository is generated from the Lynx monorepo on every release. Pull requests here are closed; send changes to [LynxCMS/lynxcms](https://github.com/LynxCMS/lynxcms) instead.

## Run it

You need PHP 8.5, Composer and Node.

```bash
git clone https://github.com/LynxCMS/starter-training.git atlas
cd atlas
composer setup
php artisan db:seed
composer dev
```

`composer setup` installs the dependencies, creates `.env` and the SQLite database, runs the migrations and builds the CSS and JavaScript. `php artisan db:seed` adds the demo school and an owner account. `composer dev` serves the site at http://localhost:8000.

Sign in to the admin at http://localhost:8000/lynx with `owner@atlas-training.example` and the password `change-me-now` (set `ATLAS_OWNER_PASSWORD` in `.env` before seeding to choose another). Change it under **Account security** before you put the site anywhere others can reach.

## Start over

```bash
php artisan migrate:fresh --seed
```

## License

MIT. BlatUI's components and the Bloom look are MIT licensed by BlatUI.
