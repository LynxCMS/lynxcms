<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Fields\HtmlCleaner;
use LynxCms\Core\Fields\Types\TextareaType;

/*
 * Rich text fields offer extra tools only when their settings list them (ADR-0107): super and
 * subscript, underline, highlight, code, code blocks, horizontal rules and text alignment. The
 * cleaner keeps their markup on any rich text field, and of styles only text alignment.
 */

beforeEach(function (): void {
    installLynx();
});

it('keeps the tools a rich text field turns on', function (): void {
    lynx()->fields->add('notes', 'textarea', settings: ['format' => 'html', 'tools' => ['superscript', 'subscript', 'text_align']]);

    expect(lynx()->fields->get('notes')->config()['tools'])->toBe(['superscript', 'subscript', 'text_align']);
});

it('refuses a tool it does not know', function (): void {
    expect(fn () => lynx()->fields->add('notes', 'textarea', settings: ['format' => 'html', 'tools' => ['marquee']]))
        ->toThrow(ValidationException::class);
});

it('names every tool a field can turn on', function (): void {
    expect(TextareaType::TOOLS)->toBe(['superscript', 'subscript', 'underline', 'highlight', 'code', 'code_block', 'horizontal_rule', 'text_align']);
});

it('keeps the markup of the extra tools', function (): void {
    $html = '<p>H<sub>2</sub>O and m<sup>2</sup>, <u>under</u>, <mark>marked</mark>, <code>x</code></p><pre><code>y</code></pre><hr>';

    // The sanitizer writes void elements as XHTML.
    expect(app(HtmlCleaner::class)->clean($html))->toBe(str_replace('<hr>', '<hr />', $html));
});

it('keeps text alignment and drops every other style', function (): void {
    $cleaner = app(HtmlCleaner::class);

    expect($cleaner->clean('<p style="text-align: center; color: red">Hi</p>'))->toBe('<p style="text-align: center">Hi</p>')
        ->and($cleaner->clean('<h2 style="text-align:right">Hi</h2>'))->toBe('<h2 style="text-align: right">Hi</h2>')
        ->and($cleaner->clean('<p style="background: url(https://evil.test/x)">Hi</p>'))->toBe('<p>Hi</p>')
        ->and($cleaner->clean('<p style="text-align: expression(alert(1))">Hi</p>'))->toBe('<p>Hi</p>');
});
