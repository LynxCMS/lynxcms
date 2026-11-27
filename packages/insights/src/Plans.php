<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use InvalidArgumentException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\ActionRegistry;

/**
 * Checks an insight's plan when it is stored and names each step's action as the registry
 * knows it, so a plan stored today still runs after a class is renamed.
 *
 * @internal
 */
final readonly class Plans
{
    public function __construct(private ActionRegistry $actions) {}

    /**
     * @param  list<array{action: string, arguments: array<string, mixed>}>  $plan
     * @return list<array{action: string, arguments: array<string, mixed>}>
     *
     * @throws InvalidArgumentException when a step names an action Lynx does not have
     */
    public function normalize(array $plan): array
    {
        return array_map(fn (array $step): array => ['action' => $this->name($step['action']), 'arguments' => $step['arguments']], $plan);
    }

    /**
     * The class of a stored step's action.
     *
     * @return class-string<Action>
     *
     * @throws InvalidArgumentException
     */
    public function action(string $name): string
    {
        $class = $this->actions->find($name);

        if ($class === null || ! is_subclass_of($class, Action::class)) {
            throw new InvalidArgumentException("The plan names an action Lynx does not have: [{$name}].");
        }

        return $class;
    }

    private function name(string $action): string
    {
        $name = is_subclass_of($action, Action::class) ? $action::name() : $action;
        $this->action($name);

        return $name;
    }
}
