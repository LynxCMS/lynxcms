<?php

declare(strict_types=1);

namespace LynxCms\Forms\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\SendDigest;
use Override;

/**
 * Mails the daily summary of new submissions (forms.send_digest); scheduled daily.
 *
 * @internal
 */
final class SendDigestCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:forms:digest';

    #[Override]
    protected $description = 'Mail the daily summary of new submissions for forms that send one';

    public function handle(): int
    {
        $count = Value::int(lynx()->run(SendDigest::class)->value);
        $this->components->info("Mailed summaries for {$count} forms.");

        return self::SUCCESS;
    }
}
