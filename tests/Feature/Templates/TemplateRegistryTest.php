<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/*
 * The registry reads every template's fields once per request, sharing FieldRegistry's models,
 * so a page render loads each field once however many templates it touches.
 */

beforeEach(function (): void {
    courseSite();
    app(TemplateRegistry::class)->flush();
    app(FieldRegistry::class)->flush();
});

it('lists a template\'s fields in order with their settings for this template', function (): void {
    lynx()->templates->add('event', fields: [['name' => 'title', 'required' => true], 'price', ['name' => 'summary', 'label' => 'Teaser']]);

    $fields = app(TemplateRegistry::class)->fields('event');

    expect($fields->keys()->all())->toBe(['title', 'price', 'summary'])
        ->and($fields->map(fn (TemplateField $field): int => $field->sort)->all())->toBe(['title' => 0, 'price' => 1, 'summary' => 2])
        ->and($fields->get('summary')?->settings['label'] ?? null)->toBe('Teaser')
        ->and($fields->get('title')?->field)->toBe(app(FieldRegistry::class)->get('title'));
});

it('reads every template\'s fields in a fixed number of queries and loads each field once', function (): void {
    $models = 0;
    Event::listen('eloquent.retrieved: '.Field::class, function (Model $model) use (&$models): void {
        $models++;
    });
    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach (app(TemplateRegistry::class)->all() as $template) {
        app(TemplateRegistry::class)->fields($template);
    }

    DB::disableQueryLog();

    expect(count(DB::getQueryLog()))->toBe(3)
        ->and($models)->toBe(Field::query()->count());
});
