<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LynxCms\Cache\Pages\PreBootServer;

beforeEach(function (): void {
    $this->root = testingPath('lynx-cache-preboot');
    File::deleteDirectory($this->root);
    File::ensureDirectoryExists($this->root.'/example.com/about');
    File::put($this->root.'/example.com/about/index.html', "<p>About</p>\n<!-- lynx-cache 100 0 -->\n");
    $this->config = [
        'enabled' => true,
        'driver' => 'files',
        'path' => $this->root,
        'hosts' => ['example.com'],
        'bypass_cookies' => ['lynx_bypass'],
        'ignored_parameters' => ['utm_*'],
        'edge_ttl' => 0,
        'debug' => true,
    ];
});

function serverVars(string $uri, array $extra = []): array
{
    return [...['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'example.com:443', 'REQUEST_URI' => $uri, 'QUERY_STRING' => (string) parse_url($uri, PHP_URL_QUERY)], ...$extra];
}

it('answers a stored page from the file, with no framework', function (): void {
    $answer = new PreBootServer($this->config)->answer(serverVars('/about/?utm_source=x'), []);

    expect($answer?->body)->toBe('<p>About</p>')
        ->and($answer?->headers)->toContain('Content-Type: text/html; charset=UTF-8', 'X-Lynx-Cache: HIT');
});

it('leaves the request to Laravel when it cannot answer', function (array $config, array $server, array $cookies): void {
    expect(new PreBootServer([...$this->config, ...$config])->answer(serverVars(...$server), $cookies))->toBeNull();
})->with([
    'off' => [['enabled' => false], ['/about/'], []],
    'store driver' => [['driver' => 'store'], ['/about/'], []],
    'not stored' => [[], ['/contact/'], []],
    'bypass cookie' => [[], ['/about/'], ['lynx_bypass' => '1']],
    'POST' => [[], ['/about/', ['REQUEST_METHOD' => 'POST']], []],
]);

it('leaves expired pages to Laravel', function (): void {
    File::put($this->root.'/example.com/about/index.html', "<p>About</p>\n<!-- lynx-cache 100 200 -->\n");

    expect(new PreBootServer($this->config)->answer(serverVars('/about/'), []))->toBeNull();
});

it('sends stored pages from serve.php without the Composer autoloader', function (): void {
    $compiled = $this->root.'/compiled.php';
    $script = $this->root.'/index.php';
    File::put($compiled, '<?php return '.var_export($this->config, true).';');
    File::put($script, sprintf(
        '<?php $_SERVER = [...$_SERVER, ...%s]; (require %s)(%s); echo "not sent";',
        var_export(serverVars('/about'), true),
        var_export(dirname(__DIR__, 3).'/packages/cache/serve.php', true),
        var_export($compiled, true),
    ));

    $result = Process::run([PHP_BINARY, $script]);

    expect($result->output())->toBe('<p>About</p>')
        ->and($result->errorOutput())->toBe('');
});

it('sends no body to HEAD requests and lets edge caches keep the page', function (): void {
    $answer = new PreBootServer([...$this->config, 'edge_ttl' => 30])->answer(serverVars('/about/', ['REQUEST_METHOD' => 'HEAD']), []);

    expect($answer?->body)->toBe('')
        ->and($answer?->headers)->toContain('Cache-Control: public, max-age=0, s-maxage=30');
});
