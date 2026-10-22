<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Illuminate\Support\Facades\Auth;
use LynxCms\Contracts\Actions\Actor;

/**
 * Who did something, in words: "Ana Lima", "Ana Lima via MCP", "AI agent for Ana Lima",
 * "Console". Users are looked up through the app's auth provider, once per id.
 *
 * @internal
 */
final class ActorNames
{
    /** @var array<string, string> user id => name */
    private array $users = [];

    public function label(string $kind, ?string $userId): string
    {
        $user = $userId === null ? null : $this->user($userId);

        return match ($kind) {
            Actor::USER => $user ?? 'Someone',
            Actor::MCP => $user === null ? 'MCP' : "{$user} via MCP",
            Actor::AI => $user === null ? 'AI agent' : "AI agent for {$user}",
            Actor::CLI => 'Console',
            default => 'System',
        };
    }

    private function user(string $id): string
    {
        if (! isset($this->users[$id])) {
            $user = Auth::guard()->getProvider()->retrieveById($id);
            $name = $user === null ? null : data_get($user, 'name');
            $this->users[$id] = is_string($name) && $name !== '' ? $name : "User {$id}";
        }

        return $this->users[$id];
    }
}
