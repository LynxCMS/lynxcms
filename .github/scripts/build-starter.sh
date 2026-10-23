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

composer require $(printf "lynxcms/%s:^${VERSION} " "${PACKAGES[@]}") --no-interaction --quiet
composer require anousss007/blatui --dev --no-interaction --quiet
php artisan vendor:publish --tag=blatui-foundations --quiet
php artisan blatui:add "${COMPONENTS[@]}" --quiet
npm install --silent --save-dev alpinejs @alpinejs/anchor @alpinejs/collapse @alpinejs/focus @floating-ui/dom

# The guide's code, minus the BlatUI components the monorepo keeps for its own tests
rsync -a --exclude 'resources/views/components/ui' --exclude 'starter' "$SOURCE"/ ./
cp -R "$SOURCE"/starter/. ./
php -r '$f = "bootstrap/providers.php"; $s = file_get_contents($f);
    $s = str_replace("use App\\Providers\\AppServiceProvider;", "use App\\Providers\\AppServiceProvider;\nuse App\\Providers\\AtlasServiceProvider;", $s);
    file_put_contents($f, str_replace("    AppServiceProvider::class,", "    AppServiceProvider::class,\n    AtlasServiceProvider::class,", $s));'
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php

npm run build --silent
php artisan test --compact
