<?php

declare(strict_types=1);

/*
 * Writes docs/reference/public-api.md from the @api markers of the classes in each package's src, so
 * the page never drifts from the code (AGENTS.md rule 10). With --check it writes nothing and
 * exits 1 when the page is out of date, which tests/Unit/PublicApiPageTest.php runs.
 *
 *     php tools/docs/public-api.php            # or: composer docs:api
 *     php tools/docs/public-api.php --check
 */

$root = dirname(__DIR__, 2);
$page = $root.'/docs/reference/public-api.md';
$packages = [];

foreach (glob($root.'/packages/*/src') ?: [] as $source) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() === 'php' && ($class = apiClass((string) file_get_contents($file->getPathname()))) !== null) {
            $packages[basename(dirname($source))][] = $class;
        }
    }
}

uksort($packages, fn (string $a, string $b): int => [$a !== 'contracts' && $a !== 'core', $a] <=> [$b !== 'contracts' && $b !== 'core', $b]);
$markdown = render($packages);

if (in_array('--check', $argv, true)) {
    $isCurrent = is_file($page) && file_get_contents($page) === $markdown;
    fwrite($isCurrent ? STDOUT : STDERR, $isCurrent ? "docs/reference/public-api.md is current.\n" : "docs/reference/public-api.md is out of date: run composer docs:api.\n");
    exit($isCurrent ? 0 : 1);
}

file_put_contents($page, $markdown);
echo 'Wrote docs/reference/public-api.md: '.array_sum(array_map(count(...), $packages))." classes.\n";

/**
 * The class a file declares, when its docblock marks it @api.
 *
 * @return array{name: string, kind: string, summary: string}|null
 */
function apiClass(string $php): ?array
{
    if (preg_match('/^namespace\s+([\w\\\\]+);/m', $php, $namespace) !== 1
        || preg_match('/^(?:(?:final|abstract|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)/m', $php, $class, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    $before = substr($php, 0, $class[0][1]);

    if (preg_match('/\/\*\*((?:(?!\*\/).)*)\*\/\s*(?:#\[[^\]]*\]\s*)*$/s', $before, $docblock) !== 1 || preg_match('/@api\b/', $docblock[1]) !== 1) {
        return null;
    }

    return ['name' => $namespace[1].'\\'.$class[2][0], 'kind' => $class[1][0], 'summary' => summary($docblock[1])];
}

/** The docblock's first sentence, on one line. */
function summary(string $docblock): string
{
    $lines = [];

    foreach (explode("\n", $docblock) as $line) {
        $line = trim(preg_replace('/^\s*\*\s?/', '', $line) ?? '');

        if ($line === '' && $lines !== []) {
            break;
        }

        if (str_starts_with($line, '@')) {
            break;
        }

        if ($line !== '') {
            $lines[] = $line;
        }
    }

    $text = implode(' ', $lines);
    $sentence = preg_match('/^(.+?[.!?])(?:\s|$)/', $text, $match) === 1 ? $match[1] : $text;

    // ADR numbers mean nothing on the docs site; Vue would read {{ }} and tags as its template.
    $sentence = trim((string) preg_replace(['/\s*\(ADR-\d{4}(?:(?:,| to| and)\s*ADR-\d{4})*\)/', '/,\s*ADR-\d{4}(?: to ADR-\d{4})?(?=\))/'], '', $sentence));

    return strtr($sentence, ['|' => '\|', '{' => '&#123;', '}' => '&#125;', '<' => '&lt;', '>' => '&gt;']);
}

/** @param array<string, list<array{name: string, kind: string, summary: string}>> $packages */
function render(array $packages): string
{
    $total = array_sum(array_map(count(...), $packages));
    $out = <<<MD
        ---
        title: Public API
        description: Every class marked @api, the code apps and modules may rely on.
        ---

        # Public API

        Classes marked `@api` are the ones apps and modules may rely on. From 1.0 on, a minor release does not change them in a way that breaks code that uses them ([Versioning](/prologue/versioning)). Every other class is `@internal`: Lynx's own packages use it, and it may change in any release. Your IDE shows the marker on each class's docblock.

        This page lists the {$total} `@api` classes, interfaces, traits and enums by package. It is written from the code by `composer docs:api`, and a test fails when it falls behind.

        MD;

    foreach ($packages as $package => $classes) {
        usort($classes, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $prefix = 'LynxCms\\'.str_replace(' ', '', ucwords(str_replace('-', ' ', $package))).'\\';
        $out .= "\n## lynxcms/{$package}\n\nIn `".rtrim($prefix, '\\')."`.\n\n| Name | Kind | What it is |\n| --- | --- | --- |\n";

        foreach ($classes as $class) {
            $short = str_starts_with($class['name'], $prefix) ? substr($class['name'], strlen($prefix)) : $class['name'];
            $out .= "| `{$short}` | {$class['kind']} | {$class['summary']} |\n";
        }
    }

    return $out;
}
