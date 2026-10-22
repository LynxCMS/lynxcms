<?php

declare(strict_types=1);

use Laravel\Ai\Responses\Data\ToolCall;
use LynxCms\Ai\Agents\CmsAgent;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

beforeEach(function (): void {
    courseSite();
    app()->instance(TranslationProvider::class, new FakeTranslationProvider);
});

it('plans without changing anything, then applies exactly the recorded actions', function (): void {
    CmsAgent::fake([
        new ToolCall('1', 'fields_create', ['name' => 'client', 'type' => 'text', 'label' => 'Client']),
        new ToolCall('2', 'fields_create', ['name' => 'duration_days', 'type' => 'integer', 'settings' => ['min' => 1, 'max' => 30]]),
        new ToolCall('3', 'templates_create', ['name' => 'consulting', 'from' => 'course', 'fields' => [['name' => 'client', 'required' => true], 'duration_days']]),
        'Created the consulting template from course with client and duration_days.',
    ]);

    $run = lynx()->ai()->ask('Create a template called consulting, copy the fields from course and add client and duration_days');

    expect($run->isApplied())->toBeFalse()
        ->and($run->steps)->toHaveCount(3)
        ->and(implode("\n", $run->plan()))->toContain('Create template consulting with fields: title, summary, body, price, related, client, duration_days')
        ->and(lynx()->templates->find('consulting'))->toBeNull()
        ->and(lynx()->fields->find('client'))->toBeNull();

    $run->apply();

    expect(lynx()->templates->fields('consulting')->keys()->all())->toBe(['title', 'summary', 'body', 'price', 'related', 'client', 'duration_days'])
        ->and(lynx()->templates->fields('consulting')->get('client')->required())->toBeTrue()
        ->and(ActivityEvent::query()->where('actor_kind', 'ai')->count())->toBe(3);
});

it('creates a course from Markdown and translates it to Portuguese', function (): void {
    CmsAgent::fake([
        new ToolCall('1', 'pages_save', [
            'template' => 'course',
            'parent' => '/courses',
            'names' => ['default' => 'delivery-8'],
            'values' => [
                'title' => 'Navigating Project Delivery 8th Edition',
                'summary' => 'An in-depth course on the Delivery Guide.',
                'body' => ['markdown' => "## What you learn\n\n- The **four pillars**\n- All 40 processes"],
                'price' => 249,
            ],
        ]),
        new ToolCall('2', 'pages_translate', ['page' => '/courses/delivery-8', 'to' => 'pt']),
        'Created the course and a machine translation in Portuguese for review.',
    ]);

    $run = lynx()->ai()->ask('Create a new course with this content and translate it to PT', apply: true);
    $page = lynx()->pages->get('/courses/delivery-8');

    expect($run->isApplied())->toBeTrue()
        ->and($page->getLanguageValue('default', 'body'))->toContain('<h2>What you learn</h2>')->toContain('<strong>four pillars</strong>')
        ->and($page->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Navigating Project Delivery 8th Edition')
        ->and($page->localUrl('pt'))->toBe('/pt/cursos/pt-pt-navigating-project-delivery-8th-edition');
});

it('remaps ids of pages created during planning when the plan is applied', function (): void {
    CmsAgent::fake(function ($prompt): ToolCall|string {
        static $step = 0;

        return match ($step++) {
            0 => new ToolCall('1', 'pages_save', ['template' => 'courses', 'parent' => '/', 'names' => ['default' => 'workshops'], 'values' => ['title' => 'Workshops']]),
            1 => new ToolCall('2', 'pages_save', ['template' => 'course', 'parent' => (string) (lynx()->pages->get('/workshops')->id), 'names' => ['default' => 'kickoff'], 'values' => ['title' => 'Kickoff']]),
            default => 'Done.',
        };
    });

    lynx()->ai()->ask('Create a workshops section with a kickoff workshop')->apply();

    expect(lynx()->pages->get('/workshops/kickoff'))->not->toBeNull();
});
