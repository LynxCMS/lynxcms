<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Messages people read come from language files (ADR-0141): validation errors, rule failures,
 * repeater errors, refusals and selector errors. Errors for developers (drafts, exceptions about
 * code) and console output stay in English.
 */

/** The text between the parentheses that open at $start. */
function argumentsAt(string $code, int $start): string
{
    $depth = 1;

    for ($end = $start; $depth > 0 && $end < strlen($code); $end++) {
        $depth += match ($code[$end]) {
            '(' => 1, ')' => -1, default => 0,
        };
    }

    return substr($code, $start, $end - $start - 1);
}

/** The code without what it passes to the translator. */
function withoutTranslations(string $code): string
{
    while (preg_match('/\b(?:__|trans_choice)\(/', $code, $call, PREG_OFFSET_CAPTURE) === 1) {
        $start = $call[0][1];
        $length = strlen($call[0][0]);
        $code = substr_replace($code, 'translated', $start, $length + strlen(argumentsAt($code, $start + $length)) + 1);
    }

    return $code;
}

function hardcodedMessages(string $code): int
{
    $code = withoutTranslations($code);
    $count = preg_match_all('/(?:\$fail|new AuthorizationException|new SelectorException|->error\([^,()]*,)\s*\(?\s*["\']/', $code);

    preg_match_all('/withMessages\(/', $code, $calls, PREG_OFFSET_CAPTURE);

    foreach ($calls[0] as [$call, $offset]) {
        $count += preg_match('/(?<!: string )=>\s*\[?\s*(?:["\']|sprintf\()/', argumentsAt($code, $offset + strlen($call)));
    }

    return (int) $count;
}

/** @return array<string, int> */
function hardcodedByPackage(): array
{
    $found = [];

    foreach (Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/packages/*/src') as $file) {
        $package = explode('/', substr($file->getPathname(), strlen(dirname(__DIR__, 2).'/packages/')))[0];
        $found[$package] = ($found[$package] ?? 0) + hardcodedMessages($file->getContents());
    }

    $found = array_filter($found);
    ksort($found);

    return $found;
}

it('types no new message into code', function (): void {
    expect(hardcodedByPackage())->toBe([]);
});

it('tells a typed message from a translated one', function (string $code, int $expected): void {
    expect(hardcodedMessages($code))->toBe($expected);
})->with([
    ["throw ValidationException::withMessages(['page' => 'Gone.']);", 1],
    ['throw ValidationException::withMessages(["values.{$f}" => "No {$f}."]);', 1],
    ["throw ValidationException::withMessages(['page' => Value::string(__('lynx::validation.page.missing', ['page' => 'x']))]);", 0],
    ['throw ValidationException::withMessages($errors);', 0],
    ["throw ValidationException::withMessages(array_map(fn (string \$key): string => 'invite.'.\$key, \$keys));", 0],
    ["\$fail('Enter an address.');", 1],
    ["\$fail(Value::string(__('lynx::validation.values.url')));", 0],
    ["throw new AuthorizationException('No.');", 1],
    ["\$this->error('', 'A list.');", 1],
]);
