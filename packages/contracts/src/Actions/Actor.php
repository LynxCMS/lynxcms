<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Actions;

/**
 * Who performed an action: a logged-in user, a visitor who is not signed in, the CLI, an MCP client,
 * the built-in AI agent or the system (queued jobs, scheduled tasks, code that asks for it).
 *
 * @api
 */
final readonly class Actor
{
    public const string USER = 'user';

    public const string CLI = 'cli';

    public const string MCP = 'mcp';

    public const string AI = 'ai';

    public const string SYSTEM = 'system';

    public const string GUEST = 'guest';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $kind,
        public int|string|null $id = null,
        public array $meta = [],
    ) {}

    public static function cli(): self
    {
        return new self(self::CLI);
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    /** A web request without a signed-in user: only actions open to everyone. */
    public static function guest(): self
    {
        return new self(self::GUEST);
    }

    public static function user(int|string $id): self
    {
        return new self(self::USER, $id);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function mcp(int|string|null $userId = null, array $meta = []): self
    {
        return new self(self::MCP, $userId, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function ai(int|string|null $userId = null, array $meta = []): self
    {
        return new self(self::AI, $userId, $meta);
    }
}
