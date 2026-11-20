<?php

declare(strict_types=1);

namespace LynxCms\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WritesFixture\ListedWriter;

/**
 * Without Larastan, which boots a Laravel app and leaves its handlers behind: Laravel's own types
 * are enough here. The DB facade, which needs Larastan to be typed, has its rule in ArchitectureTest.
 *
 * @extends RuleTestCase<WritesOnlyInActionsRule>
 */
final class WritesOnlyInActionsRuleTest extends RuleTestCase
{
    private const string MESSAGE = '%s() writes to the database outside an action. Make it an action, or list the class with its reason under lynxWriters in phpstan.neon.dist.';

    public function test_it_reports_writes_outside_actions_fortify_writers_and_listed_classes(): void
    {
        $this->analyse([__DIR__.'/data/writes.php'], [
            [sprintf(self::MESSAGE, 'save'), 26],
            [sprintf(self::MESSAGE, 'create'), 27],
            [sprintf(self::MESSAGE, 'delete'), 28],
        ]);
    }

    protected function getRule(): Rule
    {
        return new WritesOnlyInActionsRule([ListedWriter::class]);
    }
}
