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

`composer setup` installs the dependencies, creates `.env` and the SQLite database, runs the migrations, links the folder that holds uploaded images, and builds the CSS and JavaScript. `php artisan db:seed` adds the demo school and an owner account. `composer dev` serves the site at http://localhost:8000.

Sign in at http://localhost:8000/login with `owner@atlas-training.example` and the password `change-me-now` (set `ATLAS_OWNER_PASSWORD` in `.env` before seeding to choose another), then open the admin at http://localhost:8000/lynx. Change the password before you put the site anywhere others can reach. Visitors create their own accounts at `/register`; they see the members area and never the admin.

Payments, the CRM and the newsletter stay off until you add their keys to `.env` (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `ATLAS_CRM_URL`, `ATLAS_CRM_TOKEN`, `ATLAS_NEWSLETTER_URL`, `ATLAS_NEWSLETTER_TOKEN`). The guide explains each.

## Start over

```bash
php artisan migrate:fresh --seed
```

## License

MIT. BlatUI's components and the Bloom look are MIT licensed by BlatUI.
