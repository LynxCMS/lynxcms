<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Fields\FormatContext;
use LynxCms\Contracts\Fields\Formatter;
use LynxCms\Core\Fields\Formatters\HeadingAnchors;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\FieldValue;
use LynxCms\Core\Pages\Page;

beforeEach(fn (): Lynx => courseSite());

/** A module formatter that tries to slip a script into the output. */
final class ShoutFormatter implements Formatter
{
    public static function name(): string
    {
        return 'shout';
    }

    public static function description(): string
    {
        return 'Upper case, for tests.';
    }

    public function appliesTo(string $fieldType): bool
    {
        return in_array($fieldType, ['text', 'textarea'], true);
    }

    public function producesHtml(): bool
    {
        return true;
    }

    public function format(string $html, FormatContext $context): string
    {
        return mb_strtoupper($html)."<script>alert('{$context->field}')</script>";
    }
}

function formattedCourse(array $values): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'alpha';
    foreach (['title' => 'Alpha'] + $values as $field => $value) {
        $page->{$field} = $value;
    }
    $page->save();

    return lynx()->pages->get($page->id);
}

function rendered(Page $page, string $field): string
{
    return trim(Blade::render('{{ $page->'.$field.' }}', ['page' => $page]));
}

it('formats a field in templates and keeps the stored value raw', function (): void {
    lynx()->fields->update('summary', ['settings' => ['formatters' => ['paragraphs']]]);
    $page = formattedCourse(['summary' => "First line\nsecond line\n\nNew paragraph"]);

    expect(rendered($page, 'summary'))->toBe('<p>First line<br />second line</p><p>New paragraph</p>')
        ->and($page->getUnformatted('summary'))->toBe("First line\nsecond line\n\nNew paragraph")
        ->and((string) $page->summary)->toBe("First line\nsecond line\n\nNew paragraph")
        ->and(json_encode($page->summary))->toBe(json_encode("First line\nsecond line\n\nNew paragraph"));
});

it('reads one language unformatted', function (): void {
    $page = formattedCourse(['summary' => 'English']);
    $page->setLanguageValue('pt', 'summary', 'Português');
    $page->save();

    expect($page->getUnformatted('summary', 'pt'))->toBe('Português')
        ->and($page->getUnformatted('price'))->toBeNull();
});

it('gives shared text fields a value object that formats too', function (): void {
    lynx()->fields->add('tagline', 'text', settings: ['formatters' => ['smartypants']]);
    lynx()->templates->update('course', ['add' => ['tagline']]);
    $page = formattedCourse(['tagline' => 'Learn "risk" -- fast...']);

    expect($page->tagline)->toBeInstanceOf(FieldValue::class)
        ->and(rendered($page, 'tagline'))->toBe('Learn “risk” – fast…')
        ->and($page->getUnformatted('tagline'))->toBe('Learn "risk" -- fast...');
});

it('escapes unformatted plain text as before', function (): void {
    lynx()->fields->add('tagline', 'text');
    lynx()->templates->update('course', ['add' => ['tagline']]);

    expect(rendered(formattedCourse(['tagline' => 'Fish & <chips>']), 'tagline'))->toBe('Fish &amp; &lt;chips&gt;');
});

it('runs formatters in order after the field\'s format', function (): void {
    lynx()->fields->update('summary', ['settings' => ['formatters' => ['markdown', 'smartypants']]]);

    expect(rendered(formattedCourse(['summary' => 'A **"bold"** claim']), 'summary'))->toBe('<p>A <strong>“bold”</strong> claim</p>');
});

it('leaves tags and attributes alone when changing quotes', function (): void {
    lynx()->fields->update('body', ['settings' => ['formatters' => ['smartypants']]]);

    expect(rendered(formattedCourse(['body' => '<p>See <a href="/guide/" title="The &quot;guide&quot;">the "guide"</a></p>']), 'body'))
        ->toBe('<p>See <a href="/guide/" title="The &#34;guide&#34;">the “guide”</a></p>');
});

it('opens links to other sites in a new tab', function (): void {
    lynx()->fields->update('body', ['settings' => ['formatters' => ['external_links']]]);
    $html = rendered(formattedCourse(['body' => '<p><a href="https://example.com">out</a> and <a href="/courses/">in</a></p>']), 'body');

    expect($html)->toContain('<a href="https://example.com" target="_blank" rel="noopener">out</a>')
        ->and($html)->toContain('<a href="/courses/">in</a>');
});

it('adds noopener to the relations a link to another site already has', function (): void {
    lynx()->fields->update('body', ['settings' => ['formatters' => ['external_links']]]);

    expect(rendered(formattedCourse(['body' => '<p><a href="https://example.com" rel="sponsored">ad</a></p>']), 'body'))
        ->toContain('<a href="https://example.com" rel="sponsored noopener" target="_blank">ad</a>');
});

it('gives headings unique ids made from their text, and lists them for the link dialog', function (): void {
    lynx()->fields->update('body', ['settings' => ['formatters' => ['heading_anchors']]]);
    $body = '<h2>Opening hours</h2><p>Text</p><h3>Opening <em>hours</em></h3><h2>Ação &amp; preço</h2><h4></h4>';

    expect(rendered(formattedCourse(['body' => $body]), 'body'))
        ->toBe('<h2 id="opening-hours">Opening hours</h2><p>Text</p><h3 id="opening-hours-2">Opening <em>hours</em></h3><h2 id="acao-preco">Ação &amp; preço</h2><h4 id="section"></h4>')
        ->and(HeadingAnchors::anchors($body))->toBe([
            ['id' => 'opening-hours', 'label' => 'Opening hours'],
            ['id' => 'opening-hours-2', 'label' => 'Opening hours'],
            ['id' => 'acao-preco', 'label' => 'Ação & preço'],
            ['id' => 'section', 'label' => ''],
        ]);
});

it('cleans what formatters produce, so none can add scripts', function (): void {
    lynx()->formatters()->register(ShoutFormatter::class);
    lynx()->fields->update('summary', ['settings' => ['formatters' => ['shout']]]);

    expect(rendered(formattedCourse(['summary' => 'quiet']), 'summary'))->toBe('QUIET');
});

it('lists the formatters for a field type', function (): void {
    expect(array_keys(lynx()->formatters()->forType('textarea')))->toBe(['smartypants', 'paragraphs', 'markdown', 'external_links', 'heading_anchors'])
        ->and(array_keys(lynx()->formatters()->forType('integer')))->toBe([]);
});

it('refuses unknown formatters and formatters for another type', function (array $settings, string $type): void {
    try {
        lynx()->fields->add('extra', $type, settings: $settings);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('settings.formatters');
    }
})->with([
    'unknown' => [['formatters' => ['sparkles']], 'text'],
    'wrong type' => [['formatters' => ['paragraphs']], 'integer'],
]);
