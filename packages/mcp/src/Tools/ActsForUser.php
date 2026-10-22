<?php

declare(strict_types=1);

namespace LynxCms\Mcp\Tools;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use LynxCms\Contracts\Actions\Actor;

/**
 * MCP tools act with the permissions of the user the client authenticated as (ADR-0033); a
 * refusal becomes an error the client can show.
 *
 * @internal
 */
trait ActsForUser
{
    private function userId(Request $request): int|string|null
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @param  Closure(): Response  $read
     */
    private function asUser(Request $request, Closure $read): Response
    {
        try {
            return lynx()->actingAs(Actor::mcp($this->userId($request), ['tool' => $this->name()]), $read);
        } catch (AuthorizationException $exception) {
            return Response::error($exception->getMessage());
        }
    }
}
