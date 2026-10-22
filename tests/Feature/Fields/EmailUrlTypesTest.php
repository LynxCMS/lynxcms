<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('contact', 'email');
    lynx()->fields->add('website', 'url');
    lynx()->fields->add('booking', 'url', translatable: true, settings: ['relative' => true]);
    lynx()->templates->update('course', ['add' => ['contact', 'website', 'booking']]);
});

/** @param  array<string, mixed>  $values */
function courseWith(array $values): int
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'alpha';
    $page->title = 'Alpha';

    foreach ($values as $name => $value) {
        $page->{$name} = $value;
    }

    $page->save();

    return (int) $page->id;
}

it('stores an email address trimmed, with the domain in lower case', function (): void {
    $id = courseWith(['contact' => '  Ana.Silva@Example.PT ']);

    expect(lynx()->pages->get($id)?->contact)->toBe('Ana.Silva@example.pt')
        ->and(lynx()->pages->find('contact=Ana.Silva@example.pt')->count())->toBe(1);
});

it('refuses text that is not an email address', function (): void {
    courseWith(['contact' => 'ana at example']);
})->throws(ValidationException::class);

it('stores a web address', function (): void {
    $id = courseWith(['website' => ' https://example.com/courses?x=1 ']);

    expect(lynx()->pages->get($id)?->website)->toBe('https://example.com/courses?x=1');
});

it('accepts addresses with accented paths and domains', function (): void {
    $id = courseWith(['website' => 'https://açores.pt/formação/']);

    expect(lynx()->pages->get($id)?->website)->toBe('https://açores.pt/formação/');
});

it('refuses addresses that are not http or https', function (string $url): void {
    courseWith(['website' => $url]);
})->with(['javascript:alert(1)', 'ftp://example.com/', 'example.com', '/contact/', '//evil.example/', 'https://', 'https://exa mple.com/'])
    ->throws(ValidationException::class);

it('accepts a path on the site with "relative", per language', function (): void {
    $page = lynx()->pages->get(courseWith(['booking' => '/en/book/']));
    $page->setLanguageValue('pt', 'booking', '/reservar/');
    $page->save();

    expect((string) $page->booking)->toBe('/en/book/')
        ->and(lynx()->language('pt', fn (): string => (string) lynx()->pages->get($page->id)?->booking))->toBe('/reservar/');
});

it('refuses a protocol-relative address even with "relative"', function (): void {
    courseWith(['booking' => '//evil.example/']);
})->throws(ValidationException::class);

it('types both as strings in the PHPStan stub', function (): void {
    $path = sys_get_temp_dir().'/lynx-stub-'.uniqid().'/Page.stub';
    $this->artisan('lynx:phpstan-stub', ['--path' => $path])->assertSuccessful();
    $stub = File::get($path);
    File::deleteDirectory(dirname($path));

    expect($stub)->toContain('@property-read string|null $contact')
        ->toContain('@property-read string|null $website')
        ->toContain('@property-read \LynxCms\Core\Pages\TranslatableValue $booking');
});

it('gives editors email and url inputs', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageEditor::class, ['pageId' => courseWith([])])
        ->assertSeeHtml('type="email"')
        ->assertSeeHtml('type="url"')
        ->set('values.contact.default', 'ana@example.pt')
        ->set('values.website.default', 'https://example.pt/')
        ->call('save')
        ->assertHasNoErrors();
});
