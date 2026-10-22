<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringDefinition;
use Override;

/**
 * Lists the strings each template declares with @string, and reports keys that are not literals,
 * keys declared twice with different texts, and texts for language codes the site lacks (ADR-0047).
 */
final class ScanStringsCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:strings:scan';

    #[Override]
    protected $description = 'List the editable strings templates declare with @string and report problems';

    public function handle(StringCatalog $catalog): int
    {
        $templates = array_values(array_map(fn (Template $t): string => (string) $t->name, lynx()->templates->all()->all()));
        $rows = [];
        $problems = 0;

        foreach ($templates as $template) {
            $strings = $catalog->forTemplate($template);
            $rows[] = [$template, count(array_filter($strings, fn (StringDefinition $s): bool => ! $s->isSite())), count(array_filter($strings, fn (StringDefinition $s): bool => $s->isSite()))];

            foreach ($catalog->errors($template) as $error) {
                $this->components->warn($error);
                $problems++;
            }
        }

        foreach ($catalog->conflicts($templates) as $key => $views) {
            $this->components->warn("[{$key}] has different texts in ".implode(', ', $views).'.');
            $problems++;
        }

        $codes = lynx()->languages->all()->pluck('code')->implode(', ');

        foreach ($catalog->unknownLanguages($templates) as $key => $unknown) {
            $this->components->warn("[{$key}] has text for [".implode(', ', $unknown)."], which no language uses here (codes: {$codes}).");
            $problems++;
        }

        $this->table(['Template', 'Page strings', 'Site strings'], $rows);

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
