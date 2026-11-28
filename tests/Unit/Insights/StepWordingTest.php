<?php

declare(strict_types=1);

use LynxCms\Insights\Admin\StepWording;

/*
 * The review lists each step of a plan in the reader's language (StepWording): known actions in
 * words, any other by its name.
 */

beforeEach(function (): void {
    courseSite();
});

it('says what a translation step does, with the language\'s name', function (): void {
    app()->setLocale('pt');

    expect(app(StepWording::class)->describe(['action' => 'pages.translate', 'arguments' => ['page' => 1, 'to' => 'pt', 'draft' => true]]))
        ->toBe(__('lynx-insights::admin.plan.translate', ['language' => 'Português'], 'pt'));
});

it('names the fields a save changes, by their labels', function (): void {
    $label = (string) lynx()->fields->find('price')?->label;

    expect(app(StepWording::class)->describe(['action' => 'pages.save', 'arguments' => ['id' => 1, 'values' => ['price' => 40]]]))
        ->toBe(__('lynx-insights::admin.plan.save', ['fields' => $label]));
});

it('names any other action', function (): void {
    expect(app(StepWording::class)->describe(['action' => 'menus.save', 'arguments' => []]))
        ->toBe(__('lynx-insights::admin.plan.other', ['action' => 'menus.save']));
});
