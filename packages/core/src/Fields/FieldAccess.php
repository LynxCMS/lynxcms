<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;

/**
 * Field access per role (ADR-0151): a field with an "access" setting is managed, and only the roles
 * it lists may see it ("view") or edit it ("edit"); a role that may edit may also see. With no role
 * listed, only superusers may. A field without the setting is open to every role that may see or
 * edit the page. Superusers and the site's code (the console, the system) always may. It governs
 * the admin, pages.save and agents, never visitors or templates.
 *
 * @api
 */
final readonly class FieldAccess
{
    public const string SETTING = 'access';

    public function __construct(private Authorizer $authorizer, private ActionRunner $runner) {}

    public function canView(Field $field, ?Actor $actor = null): bool
    {
        $roles = $this->roles($field);

        return $roles === null || $this->holdsOneOf($actor, [...$roles['view'], ...$roles['edit']]);
    }

    public function canEdit(Field $field, ?Actor $actor = null): bool
    {
        $roles = $this->roles($field);

        return $roles === null || $this->holdsOneOf($actor, $roles['edit']);
    }

    /** Whether the field manages its access: only the roles it lists see or edit it. */
    public function isManaged(Field $field): bool
    {
        return $this->roles($field) !== null;
    }

    /**
     * @param  list<string>  $roles
     */
    private function holdsOneOf(?Actor $actor, array $roles): bool
    {
        $actor ??= $this->runner->actor();

        return $this->authorizer->isSuperuser($actor) || array_intersect($this->authorizer->roleNames($actor), $roles) !== [];
    }

    /**
     * The roles a managed field lists, or null when the field doesn't manage its access.
     *
     * @return array{view: list<string>, edit: list<string>}|null
     */
    private function roles(Field $field): ?array
    {
        $access = $field->config()[self::SETTING] ?? null;

        if (! is_array($access)) {
            return null;
        }

        return ['view' => Value::strings($access['view'] ?? []), 'edit' => Value::strings($access['edit'] ?? [])];
    }
}
