<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use Override;

final class LanguagesCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:languages';

    #[Override]
    protected $description = 'List languages with their prefix and fallback chain';

    public function handle(LanguageRegistry $languages): int
    {
        $this->table(['Code', 'Name', 'Locale', 'Prefix', 'Active', 'Chain'], $languages->all()->map(fn (Language $l): array => [
            $l->code.($languages->default()->id === $l->id ? ' (default)' : ''),
            $l->name,
            $l->appLocale(),
            $l->url_prefix === '' ? '(none)' : '/'.$l->url_prefix,
            $l->is_active ? 'yes' : 'no',
            implode(' → ', array_map(fn (Language $c): string => $c->code, $languages->chain($l))),
        ])->values()->all());

        return self::SUCCESS;
    }
}
