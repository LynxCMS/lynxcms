<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Support\Value;
use Override;

/** Runs search.rebuild, e.g. after changing a field's settings. */
final class SearchIndexCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:search-index {--template= : Only pages using this template}';

    #[Override]
    protected $description = 'Rebuild the search index';

    public function handle(): int
    {
        $template = Value::nullableString($this->option('template'));
        $result = $this->working('Indexing...', fn (): ActionResult => lynx()->run(RebuildSearchIndex::class, array_filter(['template' => $template])));

        $this->components->info('Indexed '.Value::int($result->arrayValue()['pages'] ?? 0).' page(s).');

        return self::SUCCESS;
    }
}
