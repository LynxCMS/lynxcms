<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Responses\AgentResponse;
use LynxCms\Ai\Agents\CmsAgent;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * lynx()->ai(): ask the built-in agent to do something in plain language.
 *
 *   $run = lynx()->ai()->ask('Create a course from this content and translate it to PT',
 *       attachments: [Document::fromPath(storage_path('course.md'))]);
 *   echo implode("\n", $run->plan());
 *   $run->apply();
 *
 * By default the agent works in plan mode: its actions run inside a transaction that is rolled
 * back, and apply() replays them once approved (ADR-0020).
 */
final readonly class LynxAi
{
    public function __construct(
        private AgentToolkit $toolkit,
        private ActionRunner $runner,
    ) {}

    /**
     * @param  list<mixed>  $attachments  laravel/ai files, or paths to local files
     * @param  (Closure(AgentRun): bool)|null  $confirm  called with the plan after its transaction is rolled back,
     *                                                   so no one waits on a person reading it; true replays it (AgentRun::apply)
     */
    public function ask(string $prompt, array $attachments = [], bool $apply = false, ?Actor $actor = null, ?Closure $confirm = null): AgentRun
    {
        $session = new AgentSession($actor ?? Actor::ai(auth()->id(), ['prompt' => mb_substr($prompt, 0, 200)]));
        $agent = new CmsAgent($session, $this->toolkit);
        $attachments = array_map(fn (mixed $file): mixed => is_string($file) ? Document::fromPath($file) : $file, $attachments);

        DB::beginTransaction();

        try {
            // Every tool call, reads included, runs as the session's actor, not as whoever runs
            // this code (the console in a command or a queued job).
            $response = $this->runner->actingAs($session->actor, fn (): AgentResponse => $agent->prompt(
                $prompt,
                $attachments,
                provider: self::provider(),
                model: Value::nullableString(config('lynx.ai.model')),
            ));

            $run = new AgentRun($response->text, $session->steps(), $apply, $session);
            $apply ? DB::commit() : DB::rollBack();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        } finally {
            $this->runner->flushCaches();
        }

        return ! $apply && $confirm !== null && $run->steps !== [] && $confirm($run) ? $run->apply() : $run;
    }

    /**
     * The configured provider: a name, a failover list, or null for laravel/ai's default.
     *
     * @return array<mixed>|string|null
     */
    public static function provider(): array|string|null
    {
        $provider = config('lynx.ai.provider');

        return is_array($provider) || is_string($provider) ? $provider : null;
    }
}
