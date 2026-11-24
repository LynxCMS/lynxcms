<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;

/**
 * Field access per role (ADR-0151): a field's "access" setting lists the roles that may see it
 * ("view") and the roles that may edit it ("edit"). A list left out means every role that may see
 * or edit the page; a role that may edit may also see. Superusers and the site's code (the console,
 * the system) always may. It governs the admin, pages.save and agents, never visitors or templates.
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

        return $roles['view'] === null || $this->holdsOneOf($actor, [...$roles['view'], ...$roles['edit'] ?? []]);
    }

    public function canEdit(Field $field, ?Actor $actor = null): bool
    {
        $roles = $this->roles($field);

        return ($roles['edit'] === null || $this->holdsOneOf($actor, $roles['edit'])) && $this->canView($field, $actor);
    }

    /**
     * Whether the field limits who may see or edit it.
     */
    public function isLimited(Field $field): bool
    {
        $roles = $this->roles($field);

        return $roles['view'] !== null || $roles['edit'] !== null;
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
     * @return array{view: list<string>|null, edit: list<string>|null}
     */
    private function roles(Field $field): array
    {
        $access = Value::map($field->config()[self::SETTING] ?? []);
        $list = fn (string $key): ?array => isset($access[$key]) && Value::strings($access[$key]) !== [] ? Value::strings($access[$key]) : null;

        return ['view' => $list('view'), 'edit' => $list('edit')];
    }
}
