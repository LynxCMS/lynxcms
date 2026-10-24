<?php

declare(strict_types=1);

namespace LynxCms\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use LynxCms\Ai\AgentSession;
use LynxCms\Ai\Tools\ActionTool;
use LynxCms\Ai\Tools\ReadTool;
use LynxCms\Core\Agents\AgentToolkit;
use Stringable;

/**
 * The built-in Lynx agent (ADR-0020). Its tools are the read tools plus one tool per registered
 * action, exactly like the MCP server.
 */
class CmsAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly AgentSession $session,
        private readonly AgentToolkit $toolkit,
    ) {}

    public function instructions(): Stringable|string
    {
        return implode("\n\n", [
            'You manage a multilingual website built with Lynx CMS, on behalf of the user. Use the tools to do what they ask.',
            'Your changes are collected into a plan that the user reviews and approves at the end, so do not ask for confirmation between steps and do not use dry runs. '
                .'If a request is ambiguous or would delete content the user did not mention, stop and explain instead of guessing.',
            'Prefer paths such as "/courses/" over numeric ids when referring to pages. For long text from attached Markdown, pass {"markdown": "..."} as the value of html textarea fields; the server converts it. '
                .'Use pages_translate for translations instead of writing translated text yourself.',
            'End with a short summary of what the plan does.',
            AgentToolkit::usageGuide(),
            "Current site schema:\n".json_encode($this->toolkit->describeSchema(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function tools(): iterable
    {
        return [
            ...ReadTool::all($this->toolkit, $this->session),
            ...array_map(
                fn (string $action): ActionTool => new ActionTool($action, $this->session, $this->toolkit),
                array_values($this->toolkit->writeTools()),
            ),
        ];
    }
}
