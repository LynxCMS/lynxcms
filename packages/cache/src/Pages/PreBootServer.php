<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Sends stored pages of the "files" driver from public/index.php, before Laravel boots, for hosts
 * whose web server rules cannot be changed. Plain PHP: it reads the settings compiled by
 * `lynx:page-cache --compile` and applies the same RequestRules as the middleware.
 *
 * @internal
 */
final readonly class PreBootServer
{
    /**
     * @param  array{enabled?: bool, driver?: string, path?: string, hosts?: list<string>, bypass_cookies?: list<string>, ignored_parameters?: list<string>, edge_ttl?: int, debug?: bool}  $config
     */
    public function __construct(private array $config) {}

    /**
     * @param  array<string, mixed>  $server  $_SERVER
     * @param  array<string, mixed>  $cookies  $_COOKIE
     */
    public function answer(array $server, array $cookies): ?PreBootAnswer
    {
        if (($this->config['enabled'] ?? false) !== true || ($this->config['driver'] ?? 'files') !== 'files') {
            return null;
        }

        $method = $this->text($server['REQUEST_METHOD'] ?? 'GET');
        $address = $this->rules()->addressFor($method, $this->host($server), $this->path($server), $this->text($server['QUERY_STRING'] ?? ''), $cookies);
        $file = $address === null ? null : ($this->config['path'] ?? '').'/'.$address->file();
        $contents = $file !== null && is_file($file) ? file_get_contents($file) : false;
        $page = $contents === false ? null : CachedPage::fromFile($contents);

        return $page === null || $page->isExpired(time()) ? null : new PreBootAnswer($this->headers(), strtoupper($method) === 'HEAD' ? '' : $page->html);
    }

    private function rules(): RequestRules
    {
        return new RequestRules($this->config['hosts'] ?? [], $this->config['bypass_cookies'] ?? [], $this->config['ignored_parameters'] ?? []);
    }

    /** @return list<string> */
    private function headers(): array
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $headers[] = 'Cache-Control: '.CachedPage::cacheControl($this->config['edge_ttl'] ?? 0);

        if (($this->config['debug'] ?? false) === true) {
            $headers[] = 'X-Lynx-Cache: HIT';
        }

        return $headers;
    }

    /** @param array<string, mixed> $server */
    private function host(array $server): string
    {
        return (string) preg_replace('/:\d+$/', '', $this->text($server['HTTP_HOST'] ?? ''));
    }

    /** @param array<string, mixed> $server */
    private function path(array $server): string
    {
        return explode('?', $this->text($server['REQUEST_URI'] ?? '/'), 2)[0];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
