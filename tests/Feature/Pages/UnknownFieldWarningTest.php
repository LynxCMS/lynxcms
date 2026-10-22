<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('venue', 'text');
    ['alpha' => $this->alpha] = createCourses('alpha');
    Log::spy();
});

function unknownFieldPage(): Page
{
    return lynx()->pages->get('/courses/alpha');
}

it('warns once per request in debug mode when a template reads a field that does not exist', function (): void {
    config(['app.debug' => true]);
    $page = unknownFieldPage();

    expect($page->titel)->toBeNull()
        ->and($page->titel)->toBeNull()
        ->and($page->getUnformatted('titel'))->toBeNull();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Unknown field [titel]')
        && $context === ['field' => 'titel', 'template' => 'course', 'page' => '/courses/alpha']);
});

it('stays silent for fields that exist but are not on the template', function (): void {
    config(['app.debug' => true]);

    expect(unknownFieldPage()->venue)->toBeNull();

    Log::shouldNotHaveReceived('warning');
});

it('stays silent in production', function (): void {
    config(['app.debug' => false]);

    expect(unknownFieldPage()->titel)->toBeNull();

    Log::shouldNotHaveReceived('warning');
});
