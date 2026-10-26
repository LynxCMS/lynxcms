<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

use Illuminate\Support\Facades\File;
use LynxCms\Core\Support\Value;
use PhpToken;

/**
 * Finds the @string calls in a Blade file (ADR-0047) with PHP's tokenizer: keys, code texts and
 * options must be literals ('text', or ['pt' => 'texto']), so they are known without running the
 * template; only the values for placeholders (replace:) are code. Also lists the views the file
 * pulls in (@include, @extends, <x-component>, and <livewire:name> or @livewire('name') as the
 * view livewire.name, Livewire's convention), so a template's catalog follows its layout,
 * partials and components.
 *
 * @internal
 */
final class StringScanner
{
    private const array OPTIONS = ['label', 'scope', 'format'];

    /** The option whose value is code, run with the template: the values for placeholders. */
    private const string REPLACE = 'replace';

    public function scan(string $path): ScanReport
    {
        $source = File::exists($path) ? File::get($path) : '';
        $report = new ScanReport;

        foreach ($this->calls($source) as $arguments) {
            $this->define($arguments, $path, $report);
        }

        preg_match_all("/@(?:include|includeIf|includeWhen|includeFirst|extends|each)\\(\\s*(?:[^'\")]*,\\s*)?['\"]([\\w.\\-:]+)['\"]/", $source, $includes);
        preg_match_all('/<x-([a-z0-9][\w.\-]*)[\s>\/]/', $source, $components);
        preg_match_all("/<livewire:([a-z0-9][\\w.\\-]*)[\\s>\\/]|@livewire\\(\\s*['\"]([\\w.\\-]+)['\"]/", $source, $livewire);
        $report->views = array_values(array_unique([
            ...$includes[1],
            ...array_map(fn (string $name): string => 'components.'.$name, $components[1]),
            ...array_map(fn (string $name): string => 'livewire.'.$name, array_filter([...$livewire[1], ...$livewire[2]])),
        ]));

        return $report;
    }

    /**
     * The argument source of every @string(...) call.
     *
     * @return list<string>
     */
    private function calls(string $source): array
    {
        $calls = [];
        $offset = 0;

        while (($start = strpos($source, '@string(', $offset)) !== false) {
            $offset = $start + 8;

            if ($start > 0 && $source[$start - 1] === '@') {
                continue;
            }

            $depth = 1;
            $quote = null;

            for ($i = $offset; $i < strlen($source) && $depth > 0; $i++) {
                $char = $source[$i];

                if ($quote !== null) {
                    if ($char === '\\') {
                        $i++;
                    } elseif ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === "'" || $char === '"') {
                    $quote = $char;
                } elseif ($char === '(' || $char === '[') {
                    $depth++;
                } elseif ($char === ')' || $char === ']') {
                    $depth--;
                }
            }

            $calls[] = substr($source, $offset, $i - $offset - 1);
            $offset = $i;
        }

        return $calls;
    }

    private function define(string $arguments, string $path, ScanReport $report): void
    {
        $view = $this->viewFile($path);
        $values = $this->arguments($arguments);

        if ($values === null || ! is_string($values[0] ?? null) || ! array_key_exists(1, $values)) {
            $report->errors[] = "{$view}: @string({$arguments}) needs a literal key and text, e.g. @string('hero.title', 'Welcome').";

            return;
        }

        $key = $values[0];

        if (preg_match('/^[a-z0-9][a-z0-9_.\-]{0,127}$/i', $key) !== 1) {
            $report->errors[] = "{$view}: [{$key}] is not a valid key (letters, digits, dots, dashes, underscores).";

            return;
        }

        $default = $values[1];
        $report->definitions[] = new StringDefinition(
            $key,
            is_array($default) ? array_map(Value::string(...), Value::map($default)) : Value::string($default),
            Value::nullableString($values['label'] ?? null),
            $this->scope($values['scope'] ?? null, $path),
            ($values['format'] ?? null) === 'markdown' ? 'markdown' : 'plain',
            $view,
        );
    }

    /**
     * Literal arguments: positional ones by index, named ones (label, scope, format) by name; null
     * when any argument is not a literal.
     *
     * @return array<int|string, string|array<string, string>>|null
     */
    private function arguments(string $source): ?array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize('<?php '.$source.';'),
            fn (PhpToken $token): bool => ! $token->isIgnorable() && $token->id !== T_OPEN_TAG,
        ));
        $values = [];
        $position = 0;
        $i = 0;

        while ($i < count($tokens) && $tokens[$i]->text !== ';') {
            $name = null;

            if (($tokens[$i + 1]->text ?? '') === ':' && $tokens[$i]->id === T_STRING) {
                $name = $tokens[$i]->text;
                $i += 2;
            }

            if ($name === self::REPLACE) {
                $i = $this->skipExpression($tokens, $i);

                continue;
            }

            [$value, $i] = $this->literal($tokens, $i);

            if ($value === null || ($name !== null && ! in_array($name, self::OPTIONS, true))) {
                return null;
            }

            $name === null ? $values[$position++] = $value : $values[$name] = $value;

            if (($tokens[$i]->text ?? '') === ',') {
                $i++;
            }
        }

        return $values;
    }

    /**
     * The token after an expression of any shape, such as replace: ['room' => $room->title]: the
     * next top-level comma, or the end. Its commas inside brackets and calls belong to it.
     *
     * @param  list<PhpToken>  $tokens
     */
    private function skipExpression(array $tokens, int $i): int
    {
        $depth = 0;
        $end = count($tokens);

        for (; $i < $end; $i++) {
            $text = $tokens[$i]->text;

            if ($depth === 0 && ($text === ',' || $text === ';')) {
                return $text === ',' ? $i + 1 : $i;
            }

            $depth += match ($text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };
        }

        return $i;
    }

    /**
     * A string literal or an array of string literals ['code' => 'text'], from token $i.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{0: string|array<string, string>|null, 1: int}
     */
    private function literal(array $tokens, int $i): array
    {
        $token = $tokens[$i] ?? null;

        if ($token?->id === T_CONSTANT_ENCAPSED_STRING) {
            return [$this->text($token->text), $i + 1];
        }

        if ($token?->text !== '[') {
            return [null, $i + 1];
        }

        return $this->map($tokens, $i + 1);
    }

    /**
     * An array of string literals ['code' => 'text'], from the token after its "[".
     *
     * @param  list<PhpToken>  $tokens
     * @return array{0: array<string, string>|null, 1: int}
     */
    private function map(array $tokens, int $i): array
    {
        $map = [];

        while (($tokens[$i]->text ?? ']') !== ']') {
            if (! $this->isPair($tokens, $i)) {
                return [null, $i];
            }

            $map[$this->text($tokens[$i]->text)] = $this->text($tokens[$i + 2]->text);
            $i += 3;

            if (($tokens[$i]->text ?? '') === ',') {
                $i++;
            }
        }

        return [$map, $i + 1];
    }

    /**
     * Whether token $i starts 'key' => 'text'.
     *
     * @param  list<PhpToken>  $tokens
     */
    private function isPair(array $tokens, int $i): bool
    {
        return ($tokens[$i]->id ?? null) === T_CONSTANT_ENCAPSED_STRING
            && ($tokens[$i + 1]->id ?? null) === T_DOUBLE_ARROW
            && ($tokens[$i + 2]->id ?? null) === T_CONSTANT_ENCAPSED_STRING;
    }

    /** The file as editors and developers know it: "layouts/site.blade.php", below the views folder. */
    private function viewFile(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $views = strrpos($path, '/views/');

        return $views === false ? basename($path) : substr($path, $views + strlen('/views/'));
    }

    /** The scope named, else the default for the view's folder (ADR-0051). */
    private function scope(mixed $named, string $path): string
    {
        return match ($named) {
            StringDefinition::SITE, StringDefinition::PAGE => $named,
            default => StringDefinition::defaultScopeFor($path),
        };
    }

    /** The value of a quoted PHP string literal. */
    private function text(string $literal): string
    {
        $body = substr($literal, 1, -1);

        return $literal[0] === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $body) : stripcslashes($body);
    }
}
