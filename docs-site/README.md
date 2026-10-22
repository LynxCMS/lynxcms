# Lynx documentation site

A minimal Laravel app that serves the Markdown in `../docs` with [Laradocs](https://github.com/PeteBishwhip/laradocs).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Open `http://127.0.0.1:8000/docs`. Settings (title, accent colour, GitHub and edit links) are in `config/laradocs.php`. To deploy, serve this app like any Laravel app; it needs no database.
