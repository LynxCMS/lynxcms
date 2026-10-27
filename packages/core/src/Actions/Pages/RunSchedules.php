<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * Applies the scheduled publishing that is due (ADR-0036), oldest first. An entry that cannot
 * run, such as one for a locked page, is marked failed with the reason and not tried again; the
 * others still run. lynx:schedule:run calls it every minute.
 */
final class RunSchedules extends Action
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    public static function name(): string
    {
        return 'pages.run_schedules';
    }

    public static function description(): string
    {
        return 'Publish and unpublish the pages whose scheduled time has come.';
    }

    /** Run by the scheduler; not something to call from MCP or the agent. */
    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array{done: int, failed: int}
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $result = ['done' => 0, 'failed' => 0];
        $due = DB::table(Tables::SCHEDULES)->whereNull('failed_at')->where('at', '<=', now())->orderBy('at')->orderBy('id')->get();

        foreach ($due as $entry) {
            try {
                $this->apply($entry, $context);
                DB::table(Tables::SCHEDULES)->where('id', $entry->id)->delete();
                $result['done']++;
            } catch (ValidationException|AuthorizationException|ActionHalted $e) {
                DB::table(Tables::SCHEDULES)->where('id', $entry->id)->update(['error' => $e->getMessage(), 'failed_at' => now()]);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function apply(stdClass $entry, ActionContext $context): void
    {
        $pageId = Value::int($entry->page_id);
        $isPublish = $entry->action === 'publish';
        $language = $this->languages->all()->firstWhere('id', Value::int($entry->language_id))
            ?? throw ValidationException::withMessages(['language' => 'The language no longer exists.']);

        $language->code === $this->languages->default()->code
            ? $context->run(SetPageStatus::class, ['page' => $pageId, ($isPublish ? 'remove' : 'add') => ['unpublished']])
            : $context->run(SavePage::class, ['id' => $pageId, 'names' => [$language->code => ['active' => $isPublish]]]);

        // The nested save can't say it was a schedule, or in which language a page went live.
        $action = Value::string($entry->action);
        $context->changes->updated('page', $pageId, "Run the scheduled {$action} of page {$pageId} [{$language->code}]", [
            'scheduled' => $action,
            'language' => Value::string($language->code),
        ]);
    }
}
