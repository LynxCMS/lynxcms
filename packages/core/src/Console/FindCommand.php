<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use LynxCms\Core\Console\Concerns\AsksInTerminal;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\TranslatableValue;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Value;
use Override;

use function Laravel\Prompts\text;

/**
 * Prints the pages matching a selector and the SQL it compiles to.
 */
final class FindCommand extends Command implements PromptsForMissingInput
{
    use AsksInTerminal;

    #[Override]
    protected $signature = 'lynx:find {selector : e.g. "template=course, price<300, sort=-title"} {--lang= : Language code} {--sql : Show the compiled SQL}';

    #[Override]
    protected $description = 'Find pages with a selector and show the compiled SQL';

    public function handle(PageFinder $finder, LanguageContext $context): int
    {
        $run = function () use ($finder): int {
            $selector = (string) $this->argument('selector');
            $pages = $finder->find($selector);

            $this->table(['ID', 'Template', 'Path', 'Title'], array_map(fn (Page $p): array => [
                $p->id,
                $p->template()->name,
                $p->url(),
                $this->title($p),
            ], $pages->all()));

            $this->line("  {$pages->count()} of {$pages->total()} page(s).");

            if ($this->option('sql')) {
                $this->newLine();
                $this->line($finder->compile($selector)->toSql());
            }

            return self::SUCCESS;
        };

        $lang = $this->option('lang');

        return $lang ? $context->with((string) $lang, fn (): int => $run()) : $run();
    }

    /** @return array<string, Closure(): string> */
    #[Override]
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['selector' => fn (): string => text('Selector', placeholder: 'template=course, price<300, sort=-title', required: true)];
    }

    private function title(Page $page): string
    {
        $title = $page->get('title');

        if ($title instanceof TranslatableValue) {
            return $title.($title->isFallback() ? ' ['.$title->resolvedLanguage()?->code.']' : '');
        }

        return Value::string($title);
    }
}
