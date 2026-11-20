<?php

declare(strict_types=1);

namespace LynxCms\Tests\PHPStan;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use LynxCms\Core\Actions\Action;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Every write is an action (AGENTS.md rule 1): actions check permissions and record activity, so
 * a database write anywhere else skips both. This reports Eloquent and query builder writes made
 * outside an action, outside a class that implements one of Fortify's write contracts (the app's
 * users are written the Fortify way), and outside the classes listed in phpstan.neon.dist
 * (lynxWriters), each with its reason. The DB facade has the same rule in ArchitectureTest.
 *
 * @implements Rule<CallLike>
 */
final readonly class WritesOnlyInActionsRule implements Rule
{
    private const array WRITES = [
        'save', 'savequietly', 'push', 'pushquietly', 'update', 'updatequietly', 'updateorcreate', 'updateorinsert',
        'delete', 'deletequietly', 'forcedelete', 'forcedeletequietly', 'restore', 'restorequietly', 'truncate',
        'create', 'createquietly', 'forcecreate', 'forcecreatequietly', 'createmany', 'createmanyquietly', 'firstorcreate',
        'insert', 'insertgetid', 'insertorignore', 'insertusing', 'upsert', 'increment', 'decrement',
        'incrementeach', 'decrementeach', 'touch', 'attach', 'detach', 'sync', 'syncwithoutdetaching', 'toggle',
        'updateexistingpivot', 'savemany', 'savemanyquietly',
    ];

    private const array FORTIFY_WRITERS = [
        CreatesNewUsers::class,
        ResetsUserPasswords::class,
        UpdatesUserPasswords::class,
        UpdatesUserProfileInformation::class,
    ];

    private const array WRITABLE = [Model::class, EloquentBuilder::class, QueryBuilder::class, Relation::class];

    /** @param  list<string>  $allowed  classes, and namespaces ending in a backslash */
    public function __construct(private array $allowed) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $method = $this->writeMethod($node);

        if ($method === null || ! $this->isOnWritable($node, $scope) || $this->isAllowedIn($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('%s() writes to the database outside an action. Make it an action, or list the class with its reason under lynxWriters in phpstan.neon.dist.', $method))
                ->identifier('lynx.writeOutsideAction')
                ->build(),
        ];
    }

    /** The write method called, or null for any other call. */
    private function writeMethod(Node $node): ?string
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall || ! $node->name instanceof Identifier) {
            return null;
        }

        return in_array(strtolower($node->name->name), self::WRITES, true) ? $node->name->name : null;
    }

    /** Whether the call is on a model, a builder or a relation. */
    private function isOnWritable(MethodCall|StaticCall $node, Scope $scope): bool
    {
        $type = match (true) {
            $node instanceof MethodCall => $scope->getType($node->var),
            $node->class instanceof Name => new ObjectType($scope->resolveName($node->class)),
            default => $scope->getType($node->class),
        };

        return array_any(self::WRITABLE, fn (string $class): bool => $this->isA($type, $class));
    }

    private function isA(Type $type, string $class): bool
    {
        return new ObjectType($class)->isSuperTypeOf($type)->yes();
    }

    /** Whether the class the call is in may write: an action, a Fortify writer, or a listed class. */
    private function isAllowedIn(Scope $scope): bool
    {
        $class = $scope->getClassReflection();

        if ($class === null) {
            return false;
        }

        $name = $class->getName();

        return $class->isSubclassOf(Action::class)
            || array_any(self::FORTIFY_WRITERS, fn (string $contract): bool => $class->implementsInterface($contract))
            || array_any($this->allowed, fn (string $allowed): bool => $name === $allowed || (str_ends_with($allowed, '\\') && str_starts_with($name, $allowed)));
    }
}
