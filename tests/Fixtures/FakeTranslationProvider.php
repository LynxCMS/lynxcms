<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use LynxCms\Contracts\Translation\TranslationProvider;
use RuntimeException;

/** Prefixes each text with the target locale, keeping HTML intact. */
final class FakeTranslationProvider implements TranslationProvider
{
    /** @var list<array<string, string>> */
    public array $calls = [];

    /** @var list<array<string, mixed>> the options of each call */
    public array $options = [];

    /** How many of the next calls fail, as a provider that cannot be reached would. */
    public int $failures = 0;

    public function name(): string
    {
        return 'fake';
    }

    public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
    {
        if ($this->failures > 0) {
            $this->failures--;

            throw new RuntimeException('cURL error 28: Operation timed out');
        }

        $this->calls[] = $texts;
        $this->options[] = $options;

        return array_map(fn (string $t): string => preg_replace('/^(<p>)?/', '$1['.$to.'] ', $t, 1) ?? $t, $texts);
    }
}
