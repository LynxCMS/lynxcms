<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Auth\Access\AuthorizationException;
use LynxCms\Contracts\Actions\Actor;

/**
 * Who may change the schema (ADR-0117). With lynx.schema_editing off, the site's schema comes from
 * files (lynx:schema:apply): the runner refuses every action that changes the schema to people,
 * MCP clients and the agent, and the admin shows its schema screens read only. The command line
 * and the system still change it, so applying the files and installing work. Schema a module owns
 * (ADR-0115) is never edited by hand.
 *
 * @api
 */
final readonly class SchemaEditing
{
    /** Actors that apply the schema files or install, rather than edit by hand. */
    private const array APPLIERS = [Actor::CLI, Actor::SYSTEM];

    public function __construct(private SchemaOwners $owners) {}

    public function isEnabled(): bool
    {
        return (bool) config('lynx.schema_editing', true);
    }

    public function allows(Actor $actor): bool
    {
        return $this->isEnabled() || in_array($actor->kind, self::APPLIERS, true);
    }

    /**
     * @throws AuthorizationException when the actor may not change the schema here
     */
    public function authorize(Actor $actor, string $action): void
    {
        if (! $this->allows($actor)) {
            throw new AuthorizationException("[{$action}] changes the schema, and schema editing is turned off on this site (lynx.schema_editing): its schema comes from files. Change lynx/schema and run php artisan lynx:schema:apply.");
        }
    }

    public function canEditTemplate(string $template): bool
    {
        return $this->isEnabled() && $this->owners->ownerOfTemplate($template) === null;
    }

    public function canEditField(string $field): bool
    {
        return $this->isEnabled() && $this->owners->ownerOfField($field) === null;
    }
}
