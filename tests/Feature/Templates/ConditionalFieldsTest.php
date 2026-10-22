<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('format', 'text');
    lynx()->fields->add('venue', 'text');
    lynx()->templates->add('event', fields: [
        ['name' => 'title', 'required' => true],
        'format',
        ['name' => 'venue', 'required' => true, 'show_if' => 'format=onsite|hybrid'],
        ['name' => 'summary', 'required_if' => 'format=online'],
    ]);
});

/** @param  array<string, mixed>  $values */
function saveEvent(array $values): Page
{
    $page = lynx()->pages->new('event', '/');
    $page->name = 'event';
    foreach (['title' => 'Kickoff'] + $values as $field => $value) {
        $page->{$field} = $value;
    }
    $page->save();

    return $page;
}

it('does not require a field its show_if hides', function (): void {
    expect(saveEvent(['format' => 'online', 'summary' => 'Zoom'])->id)->toBeInt();
});

it('requires a required field while its show_if matches', function (): void {
    expect(fn (): Page => saveEvent(['format' => 'onsite']))->toThrow(ValidationException::class, 'Venue is required');
});

it('requires a field while its required_if matches', function (): void {
    expect(fn (): Page => saveEvent(['format' => 'online']))->toThrow(ValidationException::class, 'Summary is required');
    expect(saveEvent(['format' => 'onsite', 'venue' => 'Lisbon'])->id)->toBeInt();
});

it('reads conditions from the default language', function (): void {
    lynx()->fields->add('mode', 'text', translatable: true);
    lynx()->templates->update('event', ['add' => ['mode', ['name' => 'price', 'required_if' => 'mode=paid']]]);
    $page = lynx()->pages->new('event', '/');
    $page->name = 'event';
    $page->title = 'Kickoff';
    $page->format = 'online';
    $page->summary = 'Zoom';
    $page->setLanguageValue('pt', 'mode', 'paid');

    expect($page->save())->toBeInstanceOf(Page::class);
});

it('refuses conditions it cannot read or that name fields the template lacks', function (array $field, string $message): void {
    expect(fn () => lynx()->templates->add('broken', fields: [['name' => 'title'], 'format', $field]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'syntax' => [['name' => 'venue', 'show_if' => 'format==onsite'], 'show_if of [venue]'],
    'unknown field' => [['name' => 'venue', 'required_if' => 'price>0'], 'not on this template'],
    'itself' => [['name' => 'venue', 'show_if' => 'venue=x'], 'cannot depend on itself'],
]);
