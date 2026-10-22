<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\AdminSearch;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    test()->actingAs(new GenericUser(['id' => 1]));
});

it('shows the search box in the top bar', function (): void {
    $this->get('/lynx/pages')->assertSeeLivewire(AdminSearch::class);
});

it('finds pages by any searchable text, not only the title', function (): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true]]]);
    $beta = lynx()->pages->get('/courses/beta/');
    $beta->summary = 'Filmado ao longo das quatro estações';
    $beta->save();

    Livewire::test(AdminSearch::class)
        ->set('query', 'quatro estacoes')
        ->assertSee('Beta')
        ->assertDontSee('Alpha');
});

it('searches titles for plain text and links to the editor', function (): void {
    Livewire::test(AdminSearch::class)
        ->set('query', 'alp')
        ->assertSee('Alpha')
        ->assertSee('/courses/alpha/')
        ->assertDontSee('Beta')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', lynx()->pages->get('/courses/alpha/')?->id).'"');
});

it('runs a selector as in code', function (): void {
    Livewire::test(AdminSearch::class)
        ->set('query', 'template=course, sort=-title')
        ->assertSeeInOrder(['Beta', 'Alpha'])
        ->assertDontSee('Courses');
});

it('finds hidden and unpublished pages unless the selector says otherwise', function (): void {
    lynx()->pages->setStatus(lynx()->pages->get('/courses/beta/'), add: ['unpublished']);

    Livewire::test(AdminSearch::class)->set('query', 'template=course')->assertSee('Beta');
    Livewire::test(AdminSearch::class)->set('query', 'template=course, include=hidden')->assertDontSee('Beta');
});

it('says why a selector is wrong', function (): void {
    Livewire::test(AdminSearch::class)
        ->set('query', 'colour=red')
        ->assertSee('Unknown field [colour] in selector.');
});

it('caps how many pages a selector can ask for, and says how many matched', function (): void {
    createCourses(...array_map(fn (int $i): string => 'course-'.$i, range(1, 12)));

    Livewire::test(AdminSearch::class)
        ->set('query', 'template=course')
        ->assertViewHas('results', fn (array $results): bool => count($results) === AdminSearch::LIMIT)
        ->assertSee('10 of 14');

    Livewire::test(AdminSearch::class)
        ->set('query', 'template=course, limit=100000')
        ->assertViewHas('results', fn (array $results): bool => count($results) === 14);
});

it('shows only pages the user may view', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses/alpha/')?->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['courses-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    Livewire::test(AdminSearch::class)
        ->set('query', 'template=course')
        ->assertSee('Alpha')
        ->assertDontSee('Beta');
});

it('turns text into an index search and keeps selectors', function (string $query, string $selector): void {
    expect(AdminSearch::selector($query))->toBe($selector);
})->with([
    'text' => ['about us', 'search*="about us", include=all'],
    'quotes removed' => ['say "hi"', 'search*="say hi", include=all'],
    'selector' => ['template=course', 'template=course, include=all'],
    'comparison' => ['price>10', 'price>10, include=all'],
    'own include' => ['template=course, include=hidden', 'template=course, include=hidden'],
]);
