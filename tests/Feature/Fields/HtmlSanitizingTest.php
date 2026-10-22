<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;

beforeEach(fn (): Lynx => courseSite());

/**
 * @param  string|array{markdown: string}  $html
 */
function storedBody(string|array $html): string
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'html-'.md5(json_encode($html) ?: '');
    $page->title = 'HTML';
    $page->body = $html;
    $page->save();

    return (string) lynx()->pages->get($page->id)->getLanguageValue('default', 'body');
}

it('removes scripts, event handlers and unsafe links from html fields', function (string $dirty, string $gone): void {
    expect(storedBody($dirty))->not->toContain($gone);
})->with([
    'script element' => ['<p>Hi<script>alert(1)</script></p>', 'script'],
    'event handler' => ['<p onclick="alert(1)">Hi</p>', 'onclick'],
    'javascript link' => ['<p><a href="javascript:alert(1)">x</a></p>', 'javascript'],
    'iframe' => ['<iframe src="https://evil.example"></iframe><p>Hi</p>', 'iframe'],
    'inline style' => ['<p style="background:url(x)">Hi</p>', 'style'],
]);

it('keeps the markup the rich text editor produces', function (): void {
    $html = '<h2>Title</h2><p>Some <strong>bold</strong>, <em>italic</em> and <s>struck</s> text.</p>'
        .'<ul><li>One</li></ul><ol><li>Two</li></ol><blockquote><p>Quote</p></blockquote>'
        .'<pre><code>code()</code></pre><p>Line<br>break</p><hr>'
        .'<p><a href="https://example.com/page" target="_blank" rel="noopener noreferrer nofollow">Link</a> <a href="/relative/">Local</a> <a href="mailto:a@example.com">Mail</a></p>';

    $stored = storedBody($html);

    foreach (['<h2>Title</h2>', '<strong>bold</strong>', '<em>italic</em>', '<s>struck</s>', '<li>One</li>', '<ol>', '<blockquote>', '<code>code()</code>', '<br', '<hr', 'href="https://example.com/page"', 'href="/relative/"', 'href="mailto:a&#64;example.com"', 'target="_blank"'] as $kept) {
        expect($stored)->toContain($kept);
    }
});

it('cleans html converted from markdown too', function (): void {
    expect(storedBody(['markdown' => "Hello\n\n[x](javascript:alert(1))"]))->not->toContain('javascript');
});

it('leaves plain text fields untouched', function (): void {
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'plain';
    $page->title = 'Plain';
    $page->summary = 'Use <b> for bold & keep this.';
    $page->save();

    expect((string) lynx()->pages->get($page->id)->getLanguageValue('default', 'summary'))->toBe('Use <b> for bold & keep this.');
});

it('keeps the link relations editors choose and drops others', function (string $rel, string $stored): void {
    expect(storedBody('<p><a href="https://example.com/" title="Partner" rel="'.$rel.'">x</a></p>'))
        ->toBe('<p><a href="https://example.com/" title="Partner"'.$stored.'>x</a></p>');
})->with([
    'nofollow' => ['nofollow', ' rel="nofollow"'],
    'sponsored and noreferrer' => ['sponsored noreferrer', ' rel="sponsored noreferrer"'],
    'unknown dropped' => ['me author NOFOLLOW noopener', ' rel="nofollow"'],
    'none chosen' => ['', ''],
]);

it('adds noopener only to links that open a new window', function (): void {
    expect(storedBody('<p><a href="/a/" target="_blank">a</a>, <a href="/b/" target="_blank" rel="nofollow">b</a> and <a href="#top">c</a></p>'))
        ->toBe('<p><a href="/a/" target="_blank" rel="noopener">a</a>, <a href="/b/" target="_blank" rel="nofollow noopener">b</a> and <a href="#top">c</a></p>');
});

it('is not fooled by a title that mentions a new window', function (): void {
    expect(storedBody('<p><a href="/a/" title=\'see target="_blank"\'>a</a></p>'))->not->toContain('noopener');
});
