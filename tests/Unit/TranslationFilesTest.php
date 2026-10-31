<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/*
 * Every package ships English and Portuguese wording (ADR-0097). A key added to English and
 * forgotten elsewhere would show English on a Portuguese screen, with nothing to say so. Other
 * languages are held to the same standard for the files they have.
 */

/** @return array<string, array{string, string, string}> [package, language, file] for every translation of an English file */
function translatedFiles(): array
{
    $cases = [];

    foreach (Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/packages/*/lang/en') as $file) {
        $root = dirname($file->getPath());

        foreach (array_unique(['pt', ...array_map(basename(...), glob("{$root}/*", GLOB_ONLYDIR) ?: [])]) as $language) {
            if ($language === 'pt' || ($language !== 'en' && file_exists("{$root}/{$language}/{$file->getFilename()}"))) {
                $cases[basename(dirname($root))."/{$language}/{$file->getFilename()}"] = [$root, $language, $file->getFilename()];
            }
        }
    }

    return $cases;
}

/** @return array<string, string> */
function translationLines(string $path): array
{
    $lines = file_exists($path) ? require $path : [];

    return Arr::dot(is_array($lines) ? $lines : []);
}

/** @return list<string> */
function placeholdersIn(string $line): array
{
    preg_match_all('/:[a-z_]+/i', $line, $matches);
    sort($matches[0]);

    return $matches[0];
}

it('has every English key and placeholder', function (string $root, string $language, string $file): void {
    $english = translationLines("{$root}/en/{$file}");
    $translated = translationLines("{$root}/{$language}/{$file}");

    expect(array_keys($translated))->toEqualCanonicalizing(array_keys($english));

    foreach ($english as $key => $line) {
        expect(placeholdersIn($translated[$key]))->toBe(placeholdersIn($line), "{$file}: {$key}");
    }
})->with(translatedFiles());
