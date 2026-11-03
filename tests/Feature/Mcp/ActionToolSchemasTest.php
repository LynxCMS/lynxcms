<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Agents\AgentToolkit;

/*
 * MCP and the built-in agent describe every action from its description() and schema(): each
 * needs a description, a schema that serializes as a JSON object, and no property the action's
 * rules don't know, which an agent would send for nothing.
 */

it('describes every action with a schema an agent can use', function (): void {
    $toolkit = app(AgentToolkit::class);
    $actions = app(ActionRegistry::class)->all();
    expect(count($actions))->toBeGreaterThan(50);

    foreach ($actions as $name => $action) {
        $schema = (new JsonSchemaTypeFactory)->object($toolkit->schemaFor($action))->toArray();
        $ruleKeys = array_map(fn (string $key): string => explode('.', $key)[0], array_keys($action::rules()));
        $unknown = array_values(array_diff(array_keys($schema['properties'] ?? []), [...$ruleKeys, 'dry_run']));

        expect(trim($toolkit->descriptionFor($action)))->not->toBe('', "{$name} has no description")
            ->and($schema['type'] ?? null)->toBe('object', "{$name}'s schema is not an object")
            ->and(json_encode($schema))->toBeString()
            ->and($unknown)->toBe([], "{$name} offers properties its rules don't know");
    }
});
