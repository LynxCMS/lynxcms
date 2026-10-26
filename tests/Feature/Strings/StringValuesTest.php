<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Strings\StringCatalog;

/*
 * Values in texts: @string's replace: fills ":name" placeholders at render time, in the code text
 * and in editors' texts alike, and the Texts screen tells editors to keep them. Strings in a
 * Livewire component's view count for the template that places the component.
 */

beforeEach(function (): void {
    courseSite();
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('greeting', fields: ['title']);
    $page = lynx()->pages->new('greeting', '/');
    $page->name = 'greeting';
    $page->title = 'Greeting';
    $page->save();
});

function greeting(string $name, ?string $language = null): string
{
    $render = fn (): string => view('templates.greeting', ['page' => lynx()->pages->get('/greeting/'), 'name' => $name])->render();

    return $language === null ? $render() : lynx()->language($language, $render);
}

it('fills placeholders in the code text, escaping the values', function (): void {
    expect(greeting('<b>Ana</b>'))->toContain('Hello &lt;b&gt;Ana&lt;/b&gt;, you have 2 rooms')
        ->and(greeting('Ana', 'pt'))->toContain('Olá Ana, tem 2 salas');
});

it('fills placeholders in editors\' texts too', function (): void {
    lynx()->run(SaveStrings::class, ['page' => lynx()->pages->get('/greeting/')?->id, 'language' => 'pt', 'values' => ['greeting' => 'Bom dia, :name!']]);

    expect(greeting('Ana', 'pt'))->toContain('Bom dia, Ana!');
});

it('capitalises for :Name and upper-cases for :NAME, and leaves times alone', function (): void {
    expect(greeting('ana'))->toContain('Hi ANA and Ana at 10:00');
});

it('accepts code for replace: and lists the placeholders for editors', function (): void {
    $catalog = app(StringCatalog::class);
    $strings = $catalog->forTemplate('greeting');

    expect($catalog->errors('greeting'))->toBe([])
        ->and($strings['greeting']->placeholders())->toBe([':name', ':count'])
        ->and($strings['shout']->placeholders())->toBe([':NAME', ':Name'])
        ->and($strings['greeting']->default)->toBe(['default' => 'Hello :name, you have :count rooms', 'pt' => 'Olá :name, tem :count salas']);
});

it('finds the strings in the views of Livewire components a template places', function (): void {
    lynx()->templates->add('panel', fields: ['title']);
    $strings = app(StringCatalog::class)->forTemplate('panel');

    expect(array_keys($strings))->toEqualCanonicalizing(['panel.empty', 'other.note'])
        ->and($strings['other.note']->placeholders())->toBe([':who']);
});
