<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use LynxCms\Contracts\Translation\TranslationProvider;

/** Prefixes each text with the target locale, keeping HTML intact. */
final class FakeTranslationProvider implements TranslationProvider
{
    /** @var list<array<string, string>> */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
    {
        $this->calls[] = $texts;

        return array_map(fn (string $t): string => preg_replace('/^(<p>)?/', '$1['.$to.'] ', $t, 1) ?? $t, $texts);
    }
}
