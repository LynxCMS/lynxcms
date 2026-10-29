#!/usr/bin/env bash
# Builds the Atlas Training starter (LynxCMS/starter-training) from the training site guide's code
# in docs/snippets/training: a fresh Laravel app, Lynx at the given version, BlatUI, the guide's
# files, and a demo database seeded and tested.
#
#   .github/scripts/build-starter.sh 0.1.8 ../starter-training
set -euo pipefail

VERSION="${1:?Usage: build-starter.sh <lynx version> <destination>}"
DEST="${2:?Usage: build-starter.sh <lynx version> <destination>}"
SOURCE="$(cd "$(dirname "$0")/../.." && pwd)/docs/snippets/training"
PACKAGES=(core admin menus media forms seo sitemap cache mcp)
COMPONENTS=(badge tabs accordion rating avatar carousel input button)

if [ -e "$DEST" ]; then
    echo "$DEST already exists; choose an empty destination." >&2
    exit 1
fi

composer create-project laravel/laravel "$DEST" --prefer-dist --no-interaction --quiet
cd "$DEST"

# Packagist's mirrors can trail a new tag by a few minutes after its own listing shows it.
for attempt in 1 2 3 4 5 6; do
    composer require $(printf "lynxcms/%s:^${VERSION} " "${PACKAGES[@]}") --no-interaction && break
    [ "$attempt" -eq 6 ] && exit 1
    echo "Lynx ${VERSION} is not installable yet; trying again in a minute." >&2
    composer clear-cache --quiet
    sleep 60
done
composer require anousss007/blatui --dev --no-interaction --quiet
php artisan vendor:publish --tag=blatui-foundations --quiet
php artisan blatui:add "${COMPONENTS[@]}" --quiet
npm install --silent --save-dev alpinejs @alpinejs/anchor @alpinejs/collapse @alpinejs/focus @floating-ui/dom

# The guide's code, minus the BlatUI components the monorepo keeps for its own tests
rsync -a --exclude 'resources/views/components/ui' --exclude 'starter' "$SOURCE"/ ./
cp -R "$SOURCE"/starter/. ./
php -r '$f = "bootstrap/providers.php"; $s = file_get_contents($f);
    $s = str_replace("use App\\Providers\\AppServiceProvider;", "use App\\Providers\\AppServiceProvider;\nuse App\\Providers\\AtlasServiceProvider;\nuse App\\Providers\\FortifyServiceProvider;", $s);
    file_put_contents($f, str_replace("    AppServiceProvider::class,", "    AppServiceProvider::class,\n    AtlasServiceProvider::class,\n    FortifyServiceProvider::class,", $s));'
# The skeleton names itself laravel/laravel on PHP 8.3; the starter needs Lynx's PHP 8.5, and composer
# setup links the folder uploaded images live in, or an editor's first photo shows as a broken image.
php -r '$f = "composer.json"; $c = json_decode(file_get_contents($f), true);
    $c["name"] = "lynxcms/starter-training";
    $c["description"] = "Atlas Training, a bilingual wellness school site built on Lynx CMS: the finished code of the training site guide.";
    $c["keywords"] = ["lynx", "cms", "laravel", "starter", "multilingual"];
    $c["require"]["php"] = "^8.5";
    $setup = $c["scripts"]["setup"];
    array_splice($setup, array_search("@php artisan migrate --force", $setup, true) + 1, 0, ["@php artisan storage:link --force"]);
    $c["scripts"]["setup"] = $setup;
    file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");'
composer update --lock --no-interaction --quiet
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php
# phpunit.xml lists tests/Unit, and Git keeps no empty folder: a clone would fail to run tests.
touch tests/Unit/.gitkeep

npm run build --silent
php artisan test --compact
