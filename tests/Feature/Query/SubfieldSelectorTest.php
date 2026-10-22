<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\SelectorException;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('role', 'text', translatable: true);
    lynx()->fields->add('fee', 'integer');
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner'], ['name' => 'role'], ['name' => 'fee']]]);
    lynx()->templates->update('course', ['add' => ['credits']]);
    ['alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma] = createCourses('alpha', 'beta', 'gamma');
    $this->ids = ['alpha' => $alpha->id, 'beta' => $beta->id, 'gamma' => $gamma->id];

    $alpha->credits = [
        ['values' => ['partner' => [$beta->id], 'role' => ['default' => 'Direction', 'pt' => 'Realização'], 'fee' => 1200]],
        ['enabled' => false, 'values' => ['partner' => [$gamma->id], 'role' => ['default' => 'Editing']]],
    ];
    $alpha->save();
    $beta->credits = [['values' => ['partner' => [$gamma->id], 'role' => ['default' => 'Sound'], 'fee' => 300]]];
    $beta->save();
});

/** @return list<string> */
function names(string $selector): array
{
    return lynx()->pages->find('template=course, sort=name, '.$selector)->map(fn (Page $page): string => $page->name());
}

it('finds pages by a page reference inside their items', function (): void {
    expect(names('credits.partner='.$this->ids['beta']))->toBe(['alpha'])
        ->and(names('credits.partner=/courses/beta/'))->toBe(['alpha']);
});

it('ignores items that are switched off', function (): void {
    expect(names('credits.partner='.$this->ids['gamma']))->toBe(['beta']);
});

it('matches translatable subfields in the current language, with fallback', function (): void {
    expect(names('credits.role=Direction'))->toBe(['alpha'])
        ->and(names('credits.role%=realiz, language=pt'))->toBe(['alpha'])
        ->and(names('credits.role=Sound, language=pt'))->toBe(['beta'])
        ->and(names('credits.role^=Dir|Sou'))->toBe(['alpha', 'beta']);
});

it('compares numbers inside items', function (): void {
    expect(names('credits.fee>500'))->toBe(['alpha'])
        ->and(names('credits.fee<=300'))->toBe(['beta']);
});

it('counts enabled items', function (): void {
    expect(names('credits.count>0'))->toBe(['alpha', 'beta'])
        ->and(names('credits.count=0'))->toBe(['gamma'])
        ->and(names('credits.count>=2'))->toBe([]);
});

it('negates over all enabled items', function (): void {
    expect(names('credits.partner!='.$this->ids['beta']))->toBe(['beta', 'gamma']);
});

it('refuses subfields an item field does not have', function (): void {
    lynx()->pages->find('credits.price=1');
})->throws(SelectorException::class, 'no subfield [price]');

it('refuses subfields of fields that hold no items', function (): void {
    lynx()->pages->find('title.text=x');
})->throws(SelectorException::class);
