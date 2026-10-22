<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Role;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    // Someone has a role, so roles are enforced for every user.
    Role::query()->where('name', 'superuser')->firstOrFail()->assign(1);
});

/** An action that declares no permission: closed to everyone but superusers. */
final class UndeclaredAction extends Action
{
    public static function name(): string
    {
        return 'tests.undeclared';
    }

    public static function description(): string
    {
        return 'For tests.';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Input $input, ActionContext $context): string
    {
        return 'ran';
    }
}

/**
 * @param  array<string, array{templates?: list<string>, branches?: list<int>}>  $grants
 */
function userWith(int $id, array $grants): Actor
{
    $role = Role::query()->create(['name' => "role-{$id}", 'label' => "Role {$id}"]);

    foreach ($grants as $permission => $scope) {
        $role->grants()->create(['permission' => $permission, 'templates' => $scope['templates'] ?? null, 'branches' => $scope['branches'] ?? null]);
    }

    $role->assign($id);

    return Actor::user($id);
}

function actingFor(Actor $actor, Closure $write): mixed
{
    return lynx()->actingAs($actor, $write);
}

function newCourse(string $name, string $parent = '/courses/'): Page
{
    $page = lynx()->pages->new('course', $parent);
    $page->name = $name;
    $page->title = ucfirst($name);

    return $page->save();
}

it('declares a permission on every action, closed by default', function (): void {
    foreach (lynx()->actions()->all() as $class) {
        expect($class::permission())->not->toBe('', $class);
    }

    expect(fn (): mixed => actingFor(userWith(5, ['pages.edit' => []]), fn (): ActionResult => lynx()->run(UndeclaredAction::class)))->toThrow(AuthorizationException::class)
        ->and(actingFor(Actor::user(1), fn (): mixed => lynx()->run(UndeclaredAction::class)->value))->toBe('ran');
});

it('refuses schema changes without schema.manage, naming the permission', function (): void {
    expect(fn (): mixed => actingFor(userWith(5, ['pages.edit' => []]), fn () => lynx()->fields->add('extra', 'text')))
        ->toThrow(AuthorizationException::class, 'You do not have permission [schema.manage]');

    expect(actingFor(userWith(6, ['schema.manage' => []]), fn () => lynx()->fields->add('extra', 'text'))->name)->toBe('extra');
});

it('separates editing pages from creating them', function (): void {
    $editor = userWith(5, ['pages.edit' => []]);
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->title = 'Alpha edited';

    expect(actingFor($editor, fn () => $alpha->save())->id)->toBe($alpha->id)
        ->and(fn (): mixed => actingFor($editor, fn (): Page => newCourse('gamma')))->toThrow(AuthorizationException::class, '[pages.create]');
});

it('limits creating pages to granted branches and templates', function (): void {
    $scope = ['templates' => ['course'], 'branches' => [lynx()->pages->get('/courses/')->id]];
    $creator = userWith(5, ['pages.create' => $scope, 'pages.publish' => $scope]);

    expect(actingFor($creator, fn (): Page => newCourse('gamma'))->id)->toBeInt()
        ->and(fn (): mixed => actingFor($creator, fn (): Page => newCourse('delta', '/')))->toThrow(AuthorizationException::class);
});

it('needs pages.publish to publish or unpublish', function (): void {
    $editor = userWith(5, ['pages.edit' => [], 'pages.status' => []]);

    expect(fn (): mixed => actingFor($editor, fn () => lynx()->pages->setStatus('/courses/alpha/', add: ['unpublished'])))->toThrow(AuthorizationException::class, '[pages.publish]')
        ->and(actingFor($editor, fn () => lynx()->pages->setStatus('/courses/alpha/', add: ['hidden']))->id)->toBeInt();
});

it('checks moves at the source and the destination', function (): void {
    $mover = userWith(5, ['pages.move' => ['branches' => [lynx()->pages->get('/courses/')->id]]]);

    expect(fn (): mixed => actingFor($mover, fn () => lynx()->pages->move('/courses/alpha/', '/')))->toThrow(AuthorizationException::class)
        ->and(actingFor($mover, fn () => lynx()->pages->move('/courses/alpha/', '/courses/beta/'))->id)->toBeInt();
});

it('checks every page of a branch before deleting it', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    $deleter = userWith(5, ['pages.delete' => ['templates' => ['course']]]);

    expect(fn (): mixed => actingFor($deleter, fn () => lynx()->pages->delete('/courses/')))->toThrow(AuthorizationException::class)
        ->and(actingFor($deleter, fn () => lynx()->pages->delete($alpha)))->toBeArray();
});

it('needs pages.template for the page and the template switched to', function (): void {
    lynx()->templates->add('lesson', fields: ['title']);
    $switcher = userWith(5, ['pages.template' => ['templates' => ['course']]]);

    expect(fn (): mixed => actingFor($switcher, fn () => lynx()->pages->switchTemplate('/courses/alpha/', 'lesson')))->toThrow(AuthorizationException::class, 'lesson');
});

it('does not check actions another action runs', function (): void {
    $menus = userWith(5, ['menus.manage' => []]);

    expect(actingFor($menus, fn () => lynx()->menus()->create('top', 'Top'))->name())->toBe('top');
});

it('lets anyone change their own current language', function (): void {
    expect(actingFor(userWith(5, []), fn (): mixed => lynx()->run(SetCurrentLanguage::class, ['language' => 'pt'])->value))->not->toBeNull();
});

it('lets a module\'s permission cover the page actions inside its own branch', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->create('footer', 'Footer');
    $menus = userWith(5, ['menus.manage' => []]);

    expect(actingFor($menus, fn () => lynx()->pages->delete((int) lynx()->menus()->page('footer')?->id)))->toBeArray()
        ->and(fn (): mixed => actingFor($menus, fn () => lynx()->pages->delete('/courses/alpha/')))->toThrow(AuthorizationException::class, '[pages.delete]');
});
