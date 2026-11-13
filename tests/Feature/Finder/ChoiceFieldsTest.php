<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Core\Pages\Page;
use LynxCms\Finder\Admin\FinderScreen;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Kind;
use LynxCms\Finder\Search\FinderQuery;
use LynxCms\Finder\SelectorWriter;

/*
 * Choice fields in the finder: a condition picks one of the field's options (labeled in the
 * current language) and is written as the option's value, so it matches single and multiple choices.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('level', 'choice', settings: ['options' => [
        ['value' => 'basic', 'label' => ['en' => 'Basic', 'pt' => 'Básico']],
        ['value' => 'advanced', 'label' => ['en' => 'Advanced', 'pt' => 'Avançado']],
    ]]);
    lynx()->fields->add('tracks', 'choice', settings: ['multiple' => true, 'options' => [
        ['value' => 'risk', 'label' => 'Risk'],
        ['value' => 'audit', 'label' => 'Audit'],
    ]]);
    lynx()->templates->update('course', ['add' => [['name' => 'level'], ['name' => 'tracks']]]);
    $this->courses = createCourses('alpha', 'beta', 'gamma');
    $values = ['alpha' => ['level' => 'basic', 'tracks' => ['risk']], 'beta' => ['level' => 'advanced', 'tracks' => ['risk', 'audit']], 'gamma' => []];

    foreach ($values as $name => $fields) {
        foreach ($fields as $field => $value) {
            $this->courses[$name]->{$field} = $value;
        }

        $this->courses[$name]->save();
    }
});

/**
 * @param  array<string, mixed>  $condition
 * @return list<string>
 */
function choiceNames(array $condition): array
{
    $terms = app(SelectorWriter::class)->terms(FinderQuery::fromArray(['groups' => [['conditions' => [[...['value' => '', 'to' => '', 'pages' => []], ...$condition]]]]]));
    $names = lynx()->pages->find('template=course'.($terms === '' ? '' : ", {$terms}"))->map(fn (Page $page): string => $page->name());
    sort($names);

    return $names;
}

it('offers the options of choice fields, by value', function (): void {
    $level = app(ConditionCatalog::class)->find('level');

    expect($level?->kind)->toBe(Kind::Choice)
        ->and($level?->options)->toBe(['basic' => 'Basic', 'advanced' => 'Advanced'])
        ->and(Kind::Choice->operators()[0]->value)->toBe('is');
});

it('finds pages by the option they hold', function (): void {
    expect(choiceNames(['subject' => 'level', 'operator' => 'is', 'value' => 'advanced']))->toBe(['beta'])
        ->and(choiceNames(['subject' => 'level', 'operator' => 'not', 'value' => 'advanced']))->toBe(['alpha', 'gamma'])
        ->and(choiceNames(['subject' => 'tracks', 'operator' => 'is', 'value' => 'risk']))->toBe(['alpha', 'beta'])
        ->and(choiceNames(['subject' => 'level', 'operator' => 'blank']))->toBe(['gamma']);
});

it('refuses a value that is not one of the options', function (): void {
    expect(app(SelectorWriter::class)->terms(FinderQuery::fromArray(['groups' => [['conditions' => [['subject' => 'level', 'operator' => 'is', 'value' => 'basic, include=all']]]]])))->toBe('');
});

it('shows the options as the value of a choice condition', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(FinderScreen::class)
        ->set('search.groups.0.conditions.0.subject', 'level')
        ->assertSeeHtml('data-finder-value="choice"')
        ->assertSeeHtml('<option value="advanced">')
        ->set('search.groups.0.conditions.0.value', 'advanced')
        ->assertSeeHtml('data-finder-row="'.$this->courses['beta']->id.'"')
        ->assertDontSeeHtml('data-finder-row="'.$this->courses['alpha']->id.'"');
});
